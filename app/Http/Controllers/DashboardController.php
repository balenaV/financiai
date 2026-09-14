<?php

namespace App\Http\Controllers;

use App\Services\BudgetService;
use App\Services\DashboardService;
use App\Services\ReauthenticationService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        TransactionService $transactions,
        BudgetService $budgetService,
    ): View {
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'account_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'edit_account' => ['nullable', 'integer'],
            'edit_card' => ['nullable', 'integer'],
            'edit_transaction' => ['nullable', 'integer'],
            'edit_category' => ['nullable', 'integer'],
            'edit_budget' => ['nullable', 'integer'],
            'edit_debt' => ['nullable', 'integer'],
            'edit_investment' => ['nullable', 'integer'],
            'edit_goal' => ['nullable', 'integer'],
            'bud_month' => ['nullable', 'integer', 'between:1,12'],
            'bud_year' => ['nullable', 'integer', 'between:2000,2100'],
            'tx_search' => ['nullable', 'string', 'max:255'],
            'tx_type' => ['nullable', 'string', 'in:income,expense,pending'],
            'tx_category' => ['nullable', 'integer'],
            'tx_account' => ['nullable', 'integer'],
            'tx_status' => ['nullable', 'string', 'in:completed,pending,cancelled'],
            'tx_page' => ['nullable', 'integer', 'min:1'],
            'rep_sort' => ['nullable', 'string', 'in:data,desc,categoria,conta,valor'],
            'rep_dir' => ['nullable', 'string', 'in:asc,desc'],
            'rep_page' => ['nullable', 'integer', 'min:1'],
            'sec_type' => ['nullable', 'string', 'in:acesso,alteracao,alerta'],
            'sec_page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($filters['account_id'])) {
            $request->user()->accounts()->findOrFail($filters['account_id']);
        }

        $editAccount = isset($filters['edit_account'])
            ? $request->user()->accounts()->find($filters['edit_account'])
            : null;
        $editCard = isset($filters['edit_card'])
            ? $request->user()->creditCards()->find($filters['edit_card'])
            : null;
        $editTransaction = isset($filters['edit_transaction'])
            ? $request->user()->transactions()->find($filters['edit_transaction'])
            : null;
        $editCategory = isset($filters['edit_category'])
            ? $request->user()->categories()->find($filters['edit_category'])
            : null;
        $editBudget = isset($filters['edit_budget'])
            ? $request->user()->budgets()->find($filters['edit_budget'])
            : null;
        $editDebt = isset($filters['edit_debt'])
            ? $request->user()->debts()->find($filters['edit_debt'])
            : null;
        $editInvestment = isset($filters['edit_investment'])
            ? $request->user()->investments()->find($filters['edit_investment'])
            : null;
        $editGoal = isset($filters['edit_goal'])
            ? $request->user()->financialGoals()->find($filters['edit_goal'])
            : null;

        $data = $dashboard->build($request->user(), $filters);

        $pendingStatuses = ['planned', 'overdue'];
        $txStatus = match (true) {
            ($filters['tx_status'] ?? null) === 'completed' => 'completed',
            ($filters['tx_status'] ?? null) === 'cancelled' => 'cancelled',
            ($filters['tx_status'] ?? null) === 'pending' => $pendingStatuses,
            ($filters['tx_type'] ?? null) === 'pending' => $pendingStatuses,
            default => null,
        };
        $transactionsPage = $transactions->filtered($request->user(), [
            'start_date' => $data['period']['start']->toDateString(),
            'end_date' => $data['period']['end']->toDateString(),
            'search' => $filters['tx_search'] ?? null,
            'type' => in_array($filters['tx_type'] ?? null, ['income', 'expense'], true) ? $filters['tx_type'] : null,
            'status' => $txStatus,
            'category_id' => $filters['tx_category'] ?? null,
            'account_id' => $filters['tx_account'] ?? null,
        ], perPage: 8, pageName: 'tx_page');

        $budgetMonth = $filters['bud_month'] ?? now()->month;
        $budgetYear = $filters['bud_year'] ?? now()->year;
        $budgetsPage = $request->user()->budgets()->with(['category', 'user'])
            ->where('month', $budgetMonth)->where('year', $budgetYear)->get()
            ->map(fn ($budget) => ['budget' => $budget, 'metrics' => $budgetService->metrics($budget)]);

        $activityCounts = [
            'todos' => $request->user()->auditLogs()->count(),
            'acesso' => $request->user()->auditLogs()->where('category', 'acesso')->count(),
            'alteracao' => $request->user()->auditLogs()->where('category', 'alteracao')->count(),
            'alerta' => $request->user()->auditLogs()->where('category', 'alerta')->count(),
        ];
        $activityPage = $request->user()->auditLogs()
            ->when($filters['sec_type'] ?? null, fn ($q, $type) => $q->where('category', $type))
            ->latest()
            ->paginate(6, pageName: 'sec_page')
            ->withQueryString();

        return view('dashboard', [
            'dashboard' => $data,
            'filters' => $filters,
            // Provedor a oferecer no re-consentimento quando a conta não tem
            // senha utilizável (login social) — ver ReauthenticationService.
            'reauthProvider' => app(ReauthenticationService::class)->availableProvider($request->user()),
            // Lembretes reais gravados por finance:send-reminders. O sino só
            // mostrava contadores calculados na hora e nunca lia estas linhas,
            // então o aviso individual ("Parcela 3 vence em...") não chegava a
            // lugar nenhum da interface.
            'unreadNotifications' => $request->user()->unreadNotifications()->latest()->limit(5)->get(),
            'unreadNotificationCount' => $request->user()->unreadNotifications()->count(),
            'editAccount' => $editAccount,
            'editCard' => $editCard,
            'editTransaction' => $editTransaction,
            'editCategory' => $editCategory,
            'editBudget' => $editBudget,
            'editDebt' => $editDebt,
            'editInvestment' => $editInvestment,
            'editGoal' => $editGoal,
            'transactionsPage' => $transactionsPage,
            'budgetMonth' => $budgetMonth,
            'budgetYear' => $budgetYear,
            'budgetsPage' => $budgetsPage,
            'activityPage' => $activityPage,
            'activityCounts' => $activityCounts,
            'activityType' => $filters['sec_type'] ?? 'todos',
            'categories' => $request->user()->categories()->where('active', true)->orderBy('name')->get(),
            'accountTypeTiles' => [
                ['key' => 'corrente', 'type' => 'checking', 'icon' => 'bank', 'iconClass' => 'fa-solid fa-building-columns', 'label' => 'Conta corrente'],
                ['key' => 'poupanca', 'type' => 'savings', 'icon' => 'bank', 'iconClass' => 'fa-solid fa-piggy-bank', 'label' => 'Poupança'],
                ['key' => 'carteira', 'type' => 'cash', 'icon' => 'cash', 'iconClass' => 'fa-solid fa-wallet', 'label' => 'Dinheiro em espécie'],
                ['key' => 'investimento', 'type' => 'investment', 'icon' => 'chart', 'iconClass' => 'fa-solid fa-chart-line', 'label' => 'Investimento'],
                ['key' => 'outra', 'type' => 'other', 'icon' => 'wallet', 'iconClass' => 'fa-solid fa-circle-dot', 'label' => 'Outra'],
            ],
        ]);
    }
}
