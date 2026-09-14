<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Services\AccountBalanceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request, AccountBalanceService $balances): View
    {
        $accounts = $request->user()->accounts()->latest()->paginate(12)->withQueryString();
        $rows = $accounts->getCollection()->map(fn (Account $account) => [
            'account' => $account,
            'current' => $balances->current($account),
            'projected' => $balances->projected($account),
        ]);
        $accounts->setCollection($rows);

        return view('accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('accounts.form', ['account' => new Account, 'types' => AccountType::cases()]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['initial_balance'] = Money::normalize($data['initial_balance']);
        $data['active'] = $request->boolean('active', true);
        $request->user()->accounts()->create($data);

        return redirect(route('dashboard').'#contas')->with('success', 'Conta criada com sucesso.');
    }

    public function show(Request $request, Account $account, AccountBalanceService $balances): View
    {
        $this->authorize('view', $account);

        $filters = $request->validate([
            'stmt_search' => ['nullable', 'string', 'max:255'],
            'stmt_type' => ['nullable', 'in:entradas,saidas'],
            'stmt_page' => ['nullable', 'integer', 'min:1'],
        ]);

        // 5000 cobre décadas de uso real (uma conta com esse volume de
        // lançamentos já teria centenas de páginas de extrato); é só um teto
        // contra materializar uma tabela inteira em memória num request só.
        $fullHistory = collect($balances->history($account, 5000));
        $counts = [
            'todas' => $fullHistory->count(),
            'entradas' => $fullHistory->where('amount', '>', 0)->count(),
            'saidas' => $fullHistory->where('amount', '<', 0)->count(),
        ];

        $filtered = $fullHistory
            ->when(
                $filters['stmt_search'] ?? null,
                fn ($rows, $search) => $rows->filter(fn ($row) => str_contains(mb_strtolower($row['description']), mb_strtolower($search))),
            )
            ->when(
                ($filters['stmt_type'] ?? null) === 'entradas',
                fn ($rows) => $rows->where('amount', '>', 0),
            )
            ->when(
                ($filters['stmt_type'] ?? null) === 'saidas',
                fn ($rows) => $rows->where('amount', '<', 0),
            )
            ->values();

        $perPage = 8;
        $page = (int) ($filters['stmt_page'] ?? 1);
        $statementPage = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['pageName' => 'stmt_page'],
        );
        $statementPage->appends([
            'stmt_search' => $filters['stmt_search'] ?? null,
            'stmt_type' => $filters['stmt_type'] ?? null,
        ]);

        return view('accounts.show', [
            'account' => $account,
            'current' => $balances->current($account),
            'projected' => $balances->projected($account),
            'statementPage' => $statementPage,
            'stmtCounts' => $counts,
            'stmtSearch' => $filters['stmt_search'] ?? '',
            'stmtType' => $filters['stmt_type'] ?? 'todas',
        ]);
    }

    public function edit(Account $account): View
    {
        $this->authorize('update', $account);

        return view('accounts.form', ['account' => $account, 'types' => AccountType::cases()]);
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);
        $data = $request->validated();
        $data['initial_balance'] = Money::normalize($data['initial_balance']);
        $data['active'] = $request->boolean('active');
        $account->update($data);

        return redirect(route('dashboard').'#contas')->with('success', 'Conta atualizada com sucesso.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->transactions()->exists() || $account->incomingTransfers()->exists()) {
            return back()->with('error', 'A conta possui movimentações. Desative-a em vez de excluir.');
        }

        $account->delete();

        return redirect(route('dashboard').'#contas')->with('success', 'Conta excluída.');
    }

    public function archive(Account $account): RedirectResponse
    {
        $this->authorize('update', $account);
        $account->update(['active' => false]);

        return redirect(route('dashboard').'#contas')->with('success', 'Conta arquivada.');
    }

    public function restore(Account $account): RedirectResponse
    {
        $this->authorize('update', $account);
        $account->update(['active' => true]);

        return redirect(route('dashboard').'#contas')->with('success', 'Conta reativada.');
    }
}
