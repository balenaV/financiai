<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AccountBalanceService
{
    public function current(Account $account, ?CarbonInterface $until = null): string
    {
        return $this->calculate($account, [TransactionStatus::Completed->value], $until);
    }

    public function projected(Account $account, ?CarbonInterface $until = null): string
    {
        return $this->calculate($account, [
            TransactionStatus::Completed->value,
            TransactionStatus::Planned->value,
            TransactionStatus::Overdue->value,
        ], $until);
    }

    public function calculate(Account $account, array $statuses, ?CarbonInterface $until = null): string
    {
        $balance = $until && $account->initial_balance_date->gt($until)
            ? '0.00'
            : (string) $account->initial_balance;

        $source = $account->transactions()
            ->whereIn('status', $statuses)
            ->when($until, fn (Builder $query) => $query->whereDate('competence_date', '<=', $until));

        $income = (string) (clone $source)->where('type', TransactionType::Income->value)->sum('amount');
        $expense = (string) (clone $source)->where('type', TransactionType::Expense->value)->sum('amount');
        $transferDebit = (string) (clone $source)
            ->where('type', TransactionType::Transfer->value)
            ->where(fn (Builder $query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'investment_credit'))
            ->sum('amount');
        $investmentCredit = (string) (clone $source)
            ->where('type', TransactionType::Transfer->value)
            ->where('source_type', 'investment_credit')
            ->sum('amount');

        $incoming = (string) $account->incomingTransfers()
            ->where('type', TransactionType::Transfer->value)
            ->whereIn('status', $statuses)
            ->when($until, fn (Builder $query) => $query->whereDate('competence_date', '<=', $until))
            ->sum('amount');

        return bcadd(
            bcsub(
                bcadd(bcadd($balance, $income, 2), bcadd($incoming, $investmentCredit, 2), 2),
                bcadd($expense, $transferDebit, 2),
                2,
            ),
            '0',
            2,
        );
    }

    /**
     * Movimentações mais recentes da conta, mais novas primeiro, com o saldo
     * acumulado após cada uma — calculado de trás para frente a partir do
     * saldo atual, já que o recorte é sempre um bloco contíguo mais recente.
     *
     * @return array<int, array{date: CarbonInterface, description: string, amount: string, balance_after: string}>
     */
    public function history(Account $account, ?int $limit = 30): array
    {
        $outgoing = $account->transactions()
            ->where('status', TransactionStatus::Completed->value)
            ->latest('competence_date')->latest('id')
            ->when($limit, fn (Builder $query) => $query->limit($limit))
            ->get()
            ->map(fn ($transaction) => [
                'date' => $transaction->competence_date,
                'id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => $transaction->type === TransactionType::Income
                    ? (string) $transaction->amount
                    : bcmul((string) $transaction->amount, '-1', 2),
            ]);

        $incoming = $account->incomingTransfers()
            ->where('status', TransactionStatus::Completed->value)
            ->latest('competence_date')->latest('id')
            ->when($limit, fn (Builder $query) => $query->limit($limit))
            ->get()
            ->map(fn ($transaction) => [
                'date' => $transaction->competence_date,
                'id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => (string) $transaction->amount,
            ]);

        $movements = $outgoing->concat($incoming)
            ->sortBy([['date', 'desc'], ['id', 'desc']])
            ->when($limit, fn ($collection) => $collection->take($limit))
            ->values();

        $balance = $this->current($account);
        $rows = [];
        foreach ($movements as $movement) {
            $rows[] = [
                'date' => $movement['date'],
                'description' => $movement['description'],
                'amount' => $movement['amount'],
                'balance_after' => $balance,
            ];
            $balance = bcsub($balance, $movement['amount'], 2);
        }

        return $rows;
    }

    public function totalsFor(User $user, ?CarbonInterface $until = null): array
    {
        $current = '0.00';
        $projected = '0.00';

        $user->accounts()->active()->get()->each(function (Account $account) use (&$current, &$projected, $until) {
            $current = bcadd($current, $this->current($account, $until), 2);
            $projected = bcadd($projected, $this->projected($account, $until), 2);
        });

        return ['current' => Money::normalize($current), 'projected' => Money::normalize($projected)];
    }
}
