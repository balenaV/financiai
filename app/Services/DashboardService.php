<?php

namespace App\Services;

use App\Enums\DebtInstallmentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DashboardService
{
    /** Mesmo teto usado em AccountController::show e InvestmentController::show. */
    private const MAX_REPORT_ROWS = 5000;

    public function __construct(
        private readonly AccountBalanceService $balances,
        private readonly BudgetService $budgets,
        private readonly CreditCardService $creditCards,
        private readonly DebtService $debts,
        private readonly InvestmentService $investments,
    ) {}

    public function build(User $user, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);
        $accountId = $filters['account_id'] ?? null;
        $accountQuery = $user->accounts()->where('active', true)->when($accountId, fn ($q) => $q->whereKey($accountId));
        $accounts = $accountQuery->get();

        $balanceCurrent = '0.00';
        $balanceProjected = '0.00';
        foreach ($accounts as $account) {
            $balanceCurrent = bcadd($balanceCurrent, $this->balances->current($account), 2);
            $balanceProjected = bcadd($balanceProjected, $this->balances->projected($account), 2);
        }

        $periodTransactions = $user->transactions()
            ->when($accountId, fn ($q) => $q->where(fn ($inner) => $inner->where('account_id', $accountId)->orWhere('destination_account_id', $accountId)))
            ->whereBetween('competence_date', [$start, $end]);

        $income = (string) (clone $periodTransactions)
            ->where('type', TransactionType::Income->value)
            ->where('status', TransactionStatus::Completed->value)
            ->sum('amount');
        $expense = (string) (clone $periodTransactions)
            ->where('type', TransactionType::Expense->value)
            ->where('status', TransactionStatus::Completed->value)
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'credit_card_bill'))
            ->sum('amount');
        $result = bcsub($income, $expense, 2);
        $plannedIncome = Money::normalize((string) (clone $periodTransactions)
            ->where('type', TransactionType::Income->value)
            ->whereIn('status', [TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
            ->sum('amount'));
        $plannedExpense = Money::normalize((string) (clone $periodTransactions)
            ->where('type', TransactionType::Expense->value)
            ->whereIn('status', [TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'credit_card_bill'))
            ->sum('amount'));

        $debtSummary = $this->creditCards->debtSummary($user);
        $debtTotal = $debtSummary['total'];
        $invested = (string) $user->investments()->where('status', 'active')->sum('current_amount');

        $recent = $user->transactions()->with(['account', 'category', 'destinationAccount', 'creditCard'])->latest('competence_date')->limit(8)->get();
        $upcoming = $user->transactions()->with('account')
            ->whereIn('status', [TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
            ->whereBetween('due_date', [today(), today()->addDays(15)])
            ->orderBy('due_date')->limit(6)->get();
        $overdue = $user->transactions()
            ->whereIn('status', [TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
            ->whereDate('due_date', '<', today())->count();

        $goals = $user->financialGoals()->with('account')->where('status', 'active')->orderBy('deadline')->limit(4)->get();
        $budgetRows = $user->budgets()->with(['category', 'user'])
            ->where('month', $start->month)->where('year', $start->year)->get()
            ->map(fn ($budget) => ['budget' => $budget, 'metrics' => $this->budgets->metrics($budget)]);

        return [
            'period' => compact('start', 'end'),
            'summary' => [
                'balance_current' => $balanceCurrent,
                'balance_projected' => $balanceProjected,
                'income' => $income,
                'expense' => $expense,
                'result' => $result,
                'planned_income' => $plannedIncome,
                'planned_expense' => $plannedExpense,
                'forecast_income' => bcadd($income, $plannedIncome, 2),
                'forecast_expense' => bcadd($expense, $plannedExpense, 2),
                'forecast_result' => bcsub(bcadd($income, $plannedIncome, 2), bcadd($expense, $plannedExpense, 2), 2),
                'debt_total' => $debtTotal,
                'overdue_bill_count' => $debtSummary['overdue_bills'],
                'invested' => $invested,
                'net_worth' => bcsub(bcadd($balanceCurrent, $invested, 2), $debtTotal, 2),
                'upcoming_count' => $upcoming->count(),
                'overdue_count' => $overdue,
                'savings_rate' => Money::percentage($result, $income),
            ],
            'accounts' => $accounts->map(fn (Account $account) => [
                'account' => $account,
                'current' => $this->balances->current($account),
                'projected' => $this->balances->projected($account),
                'history' => $this->balances->history($account),
            ]),
            'archived_accounts' => $user->accounts()->where('active', false)->get()->map(fn (Account $account) => [
                'account' => $account,
                'current' => $this->balances->current($account),
            ]),
            'recent' => $recent,
            'upcoming' => $upcoming,
            'goals' => $goals,
            'budgets' => $budgetRows,
            'charts' => $charts = $this->charts($user, $end, $start, $accountId),
            'report_deltas' => [
                'income' => $this->delta($charts['income']),
                'expense' => $this->delta($charts['expense']),
                'result' => $this->delta(array_map(fn ($i, $e) => bcsub($i, $e, 2), $charts['income'], $charts['expense'])),
            ],
            'report_detail' => $this->reportDetail(
                $user,
                Carbon::parse($end)->subMonthsNoOverflow(5)->startOfMonth(),
                $end,
                $accountId,
                $filters['rep_sort'] ?? 'data',
                $filters['rep_dir'] ?? 'desc',
                (int) ($filters['rep_page'] ?? 1),
                $filters,
            ),
            'credit_cards' => $this->creditCardsOverview($user),
            'transactions_by_day' => $this->transactionsByDay($user),
            'forecast_months' => $this->forecastMonths($user),
            'forecast_detailed' => $this->forecastDetailed($user),
            'subscriptions' => $this->subscriptions($user),
            'categories_overview' => $this->categoriesOverview($user),
            'debt_summary' => $debtSummary,
            'debts_overview' => $this->debtsOverview($user),
            'investments_overview' => $this->investmentsOverview($user),
            'goals_overview' => $this->goalsOverview($user),
        ];
    }

    /**
     * Lista detalhada de lançamentos da aba Relatórios: mesmo recorte de 6
     * meses e mesma regra de soma dos KPIs/gráfico (só efetivadas, compras de
     * cartão fora — já entram na fatura), ordenável por coluna e paginada em
     * 8 por página. A base cabe em memória (recorte de 6 meses de um único
     * usuário), então ordenar/paginar em coleção evita um join só para
     * ordenar por nome de categoria/conta.
     */
    private function reportDetail(
        User $user,
        CarbonInterface $start,
        CarbonInterface $end,
        mixed $accountId,
        string $sort,
        string $dir,
        int $page,
        array $filters,
    ): array {
        $perPage = 8;

        $items = $user->transactions()
            ->with(['account', 'category', 'creditCard'])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->where('status', TransactionStatus::Completed->value)
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'credit_card_bill'))
            ->whereBetween('competence_date', [$start, $end])
            // Mesmo teto do extrato de conta e do histórico de investimento:
            // a ordenação/paginação acontece em memória, e o dashboard inteiro
            // passa por aqui a cada carregamento — sem limite, uma conta com
            // muitos lançamentos importados vira consumo de memória por
            // request (achado M5).
            ->limit(self::MAX_REPORT_ROWS)
            ->get();

        $sorted = match ($sort) {
            'desc' => $items->sortBy(fn ($t) => mb_strtolower($t->description)),
            'categoria' => $items->sortBy(fn ($t) => mb_strtolower($t->category?->name ?? '')),
            'conta' => $items->sortBy(fn ($t) => mb_strtolower($t->account?->name ?? $t->creditCard?->name ?? '')),
            // Centavos inteiros: ordenar por (float) num valor monetário é
            // exatamente o que a regra de dinheiro do projeto proíbe.
            'valor' => $items->sortBy(fn ($t) => Money::toMinor((string) $t->amount)),
            default => $items->sortBy(fn ($t) => $t->competence_date),
        };

        if ($dir === 'desc') {
            $sorted = $sorted->reverse();
        }
        $sorted = $sorted->values();

        $paginator = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['pageName' => 'rep_page'],
        );
        $paginator->appends(array_merge($filters, ['rep_sort' => $sort, 'rep_dir' => $dir]));

        return ['paginator' => $paginator, 'sort' => $sort, 'dir' => $dir];
    }

    /**
     * Alta/baixa/estável do mês atual contra o mês anterior, a partir da
     * mesma série de 6 meses do gráfico — sem consulta extra.
     */
    private function delta(array $series): array
    {
        // bcmath do começo ao fim: a comparação com zero decide o rótulo
        // "estável", e com float um centavo de diferença podia cair do lado
        // errado da margem (achado M4).
        $current = Money::normalize((string) ($series[count($series) - 1] ?? '0'));
        $previous = Money::normalize((string) ($series[count($series) - 2] ?? '0'));
        $diff = bcsub($current, $previous, 2);

        if (bccomp($diff, '0.00', 2) === 0) {
            return ['state' => 'estavel', 'percentage' => null];
        }

        $previousAbs = bccomp($previous, '0.00', 2) < 0 ? bcmul($previous, '-1', 2) : $previous;

        $percentage = bccomp($previousAbs, '0.00', 2) !== 0
            ? round((float) bcdiv(bcmul($diff, '100', 4), $previousAbs, 4), 1)
            : null;

        return ['state' => bccomp($diff, '0.00', 2) > 0 ? 'alta' : 'baixa', 'percentage' => $percentage];
    }

    private function categoriesOverview(User $user): Collection
    {
        return $user->categories()->withCount('transactions')->orderBy('type')->orderBy('name')->get();
    }

    private function debtsOverview(User $user): Collection
    {
        return $user->debts()->with('installments')->where('status', '!=', 'cancelled')->latest()->get()
            ->map(fn ($debt) => ['debt' => $debt, 'summary' => $this->debts->summary($debt)]);
    }

    private function investmentsOverview(User $user): Collection
    {
        return $user->investments()->with('operations')->where('status', 'active')->latest()->get()
            ->map(fn ($investment) => ['investment' => $investment, 'metrics' => $this->investments->metrics($investment)]);
    }

    private function goalsOverview(User $user): Collection
    {
        return $user->financialGoals()
            ->with(['account', 'contributions' => fn ($q) => $q->where('user_id', $user->id)->latest('contributed_at')->latest('id')])
            ->whereIn('status', ['active', 'completed'])->latest()->get()
            ->map(function ($goal) {
                $current = $goal->use_account_balance && $goal->account
                    ? $this->balances->current($goal->account)
                    : $goal->current_amount;

                return [
                    'goal' => $goal,
                    'current' => $current,
                    'remaining' => bcsub($goal->target_amount, $current, 2),
                    'percentage' => Money::percentage($current, $goal->target_amount),
                ];
            });
    }

    private function subscriptions(User $user): Collection
    {
        return $user->transactions()
            ->with(['account', 'category'])
            ->where('type', TransactionType::Expense->value)
            ->whereNotNull('recurrence_group_id')
            ->whereIn('status', [TransactionStatus::Completed->value, TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
            ->orderByDesc('due_date')
            ->get()
            ->unique('recurrence_group_id')
            ->sortBy('due_date')
            ->values();
    }

    private function creditCardsOverview(User $user): array
    {
        $cards = $user->creditCards()->where('active', true)->get();

        return $cards->map(function ($card) {
            $summary = $this->creditCards->cardSummary($card);
            $limitUsed = bcsub($card->credit_limit, $summary['available_limit'], 2);

            return [
                'card' => $card,
                'outstanding' => $summary['outstanding'],
                'available_limit' => $summary['available_limit'],
                'next_bill' => $summary['next_bill'],
                'limit_used_pct' => Money::percentage($limitUsed, $card->credit_limit),
                'bills' => $card->bills()->with('purchases')->limit(12)->get(),
            ];
        })->all();
    }

    private function transactionsByDay(User $user, int $limit = 40): Collection
    {
        return $user->transactions()
            ->with(['account', 'category', 'destinationAccount'])
            ->where('status', TransactionStatus::Completed->value)
            ->latest('competence_date')->latest('id')
            ->limit($limit)
            ->get()
            ->groupBy(fn ($transaction) => $transaction->competence_date->toDateString());
    }

    private function forecastMonths(User $user, int $months = 6): array
    {
        $rows = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $month = now()->addMonthsNoOverflow($offset)->startOfMonth();
            $query = $user->transactions()
                ->whereIn('status', [TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
                ->whereBetween('due_date', [$month, $month->copy()->endOfMonth()]);

            $income = (string) (clone $query)->where('type', TransactionType::Income->value)->sum('amount');
            $expense = (string) (clone $query)->where('type', TransactionType::Expense->value)->sum('amount');

            $rows[] = [
                'month' => $month,
                'income' => $income,
                'expense' => $expense,
                'result' => bcsub($income, $expense, 2),
            ];
        }

        return $rows;
    }

    /**
     * Previsão: lançamentos planejados ou vencidos a partir de hoje,
     * agrupados por mês com os lançamentos de cada um — é o que a aba
     * Previsão do painel precisa renderizar.
     */
    private function forecastDetailed(User $user, int $months = 6): array
    {
        $transactions = $user->transactions()
            ->with(['account', 'category'])
            ->whereIn('status', [TransactionStatus::Planned->value, TransactionStatus::Overdue->value])
            ->whereBetween('competence_date', [today(), today()->addMonthsNoOverflow($months)->endOfMonth()])
            ->orderBy('competence_date')
            ->get()
            ->groupBy(fn ($transaction) => $transaction->competence_date->format('Y-m'));

        $rows = [];
        for ($offset = 0; $offset < $months; $offset++) {
            $month = today()->addMonthsNoOverflow($offset)->startOfMonth();
            $items = $transactions->get($month->format('Y-m'), collect());
            $income = $items->where('type', TransactionType::Income)->reduce(fn ($t, $i) => bcadd($t, $i->amount, 2), '0.00');
            $expense = $items->where('type', TransactionType::Expense)->reduce(fn ($t, $i) => bcadd($t, $i->amount, 2), '0.00');

            $rows[] = [
                'month' => $month,
                'income' => $income,
                'expense' => $expense,
                'result' => bcsub($income, $expense, 2),
                'transactions' => $items->sortBy('competence_date')->values(),
            ];
        }

        return $rows;
    }

    private function period(array $filters): array
    {
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            return [Carbon::parse($filters['start_date'])->startOfDay(), Carbon::parse($filters['end_date'])->endOfDay()];
        }

        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);
        $start = Carbon::create($year, $month)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    private function charts(User $user, CarbonInterface $end, CarbonInterface $periodStart, mixed $accountId): array
    {
        $months = collect(range(5, 0))->map(fn ($offset) => Carbon::parse($end)->subMonthsNoOverflow($offset)->startOfMonth());
        $income = [];
        $expense = [];
        $balances = [];
        $debtEvolution = [];

        foreach ($months as $month) {
            $query = $user->transactions()
                ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
                ->where('status', TransactionStatus::Completed->value)
                ->whereBetween('competence_date', [$month, $month->copy()->endOfMonth()]);
            $income[] = (string) (clone $query)->where('type', TransactionType::Income->value)->sum('amount');
            $expense[] = (string) (clone $query)
                ->where('type', TransactionType::Expense->value)
                ->where(fn ($inner) => $inner->whereNull('source_type')->orWhere('source_type', '!=', 'credit_card_bill'))
                ->sum('amount');
            $monthBalance = '0.00';
            foreach ($user->accounts()->when($accountId, fn ($q) => $q->whereKey($accountId))->get() as $account) {
                $monthBalance = bcadd($monthBalance, $this->balances->current($account, $month->copy()->endOfMonth()), 2);
            }
            $balances[] = $monthBalance;
            $paidUntil = (string) $user->debtInstallments()->where('status', DebtInstallmentStatus::Paid->value)
                ->whereDate('paid_at', '<=', $month->copy()->endOfMonth())->sum('amount');
            $allDebt = (string) $user->debtInstallments()->whereDate('due_date', '<=', $month->copy()->endOfMonth())->sum('amount');
            $debtEvolution[] = bcsub($allDebt, $paidUntil, 2);
        }

        $categoryExpenses = $user->transactions()->with('category')
            ->where('type', TransactionType::Expense->value)
            ->where('status', TransactionStatus::Completed->value)
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'credit_card_bill'))
            ->whereBetween('competence_date', [$periodStart, Carbon::parse($periodStart)->endOfMonth()])
            ->get()->groupBy(fn ($transaction) => $transaction->category?->name ?? 'Sem categoria')
            ->map(fn ($items) => $items->reduce(fn ($total, $item) => bcadd($total, $item->amount, 2), '0.00'));

        $investments = $user->investments()->where('status', 'active')->get()->groupBy(fn ($item) => $item->type->label())
            ->map(fn ($items) => $items->reduce(fn ($total, $item) => bcadd($total, $item->current_amount, 2), '0.00'));

        return [
            'labels' => $months->map(fn ($month) => $month->translatedFormat('M/y'))->values(),
            'labels_full' => $months->map(fn ($month) => mb_strtolower($month->translatedFormat('F')))->values(),
            'income' => $income,
            'expense' => $expense,
            'balances' => $balances,
            'debt' => $debtEvolution,
            'expense_categories' => ['labels' => $categoryExpenses->keys()->values(), 'values' => $categoryExpenses->values()],
            'investments' => ['labels' => $investments->keys()->values(), 'values' => $investments->values()],
        ];
    }
}
