<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentOperationType;
use App\Http\Requests\InvestmentOperationRequest;
use App\Http\Requests\InvestmentRequest;
use App\Models\Investment;
use App\Services\InvestmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class InvestmentController extends Controller
{
    public function store(InvestmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['invested_amount'] = Money::normalize($data['invested_amount']);
        $data['current_amount'] = Money::normalize($data['current_amount']);
        $request->user()->investments()->create($data);

        return redirect(route('dashboard').'#investimentos')->with('success', 'Investimento criado.');
    }

    private const OP_GROUPS = [
        'aportes' => [InvestmentOperationType::Contribution, InvestmentOperationType::Buy],
        'resgates' => [InvestmentOperationType::Withdrawal, InvestmentOperationType::Sell],
        'rendimentos' => [InvestmentOperationType::Yield, InvestmentOperationType::Dividend, InvestmentOperationType::ValueAdjustment],
    ];

    public function show(Request $request, Investment $investment, InvestmentService $service): View
    {
        $this->authorize('view', $investment);

        $filters = $request->validate([
            'op_type' => ['nullable', 'in:aportes,resgates,rendimentos'],
            'op_page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Teto de 5000 pelo mesmo motivo do extrato de conta (AccountController):
        // evita materializar um histórico inteiro em memória num request só.
        $allOperations = $investment->operations()->with('account')->orderByDesc('operation_date')->orderByDesc('id')->limit(5000)->get();
        $counts = collect(self::OP_GROUPS)->map(
            fn ($types) => $allOperations->whereIn('type', $types)->count(),
        )->put('todas', $allOperations->count());

        $filtered = isset(self::OP_GROUPS[$filters['op_type'] ?? null])
            ? $allOperations->whereIn('type', self::OP_GROUPS[$filters['op_type']])->values()
            : $allOperations;

        $perPage = 6;
        $page = (int) ($filters['op_page'] ?? 1);
        $operationsPage = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['pageName' => 'op_page'],
        );
        $operationsPage->appends(['op_type' => $filters['op_type'] ?? null]);

        return view('investments.show', [
            'investment' => $investment,
            'metrics' => $service->metrics($investment),
            'operationTypes' => InvestmentOperationType::cases(),
            'accounts' => $request->user()->accounts()->active()->orderBy('name')->get(),
            'operationsPage' => $operationsPage,
            'opCounts' => $counts,
            'opType' => $filters['op_type'] ?? 'todas',
        ]);
    }

    public function update(InvestmentRequest $request, Investment $investment): RedirectResponse
    {
        $this->authorize('update', $investment);
        $data = $request->validated();
        $data['invested_amount'] = Money::normalize($data['invested_amount']);
        $data['current_amount'] = Money::normalize($data['current_amount']);
        $investment->update($data);

        return redirect(route('dashboard').'#investimentos')->with('success', 'Investimento atualizado.');
    }

    public function operation(
        InvestmentOperationRequest $request,
        Investment $investment,
        InvestmentService $service,
    ): RedirectResponse {
        $this->authorize('update', $investment);
        $service->addOperation($request->user(), $investment, $request->validated());

        return back()->with('success', 'Operação registrada sem duplicidade patrimonial.');
    }

    public function destroy(Investment $investment): RedirectResponse
    {
        $this->authorize('delete', $investment);

        if ($investment->operations()->exists()) {
            return back()->with('error', 'O investimento possui operações. Marque-o como encerrado ou resgatado.');
        }

        $investment->delete();

        return redirect(route('dashboard').'#investimentos')->with('success', 'Investimento excluído.');
    }
}
