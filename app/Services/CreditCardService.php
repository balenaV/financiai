<?php

namespace App\Services;

use App\Enums\CreditCardBillStatus;
use App\Enums\DebtInstallmentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditCardService
{
    public function createBill(User $user, CreditCard $creditCard, array $data): CreditCardBill
    {
        if ($creditCard->user_id !== $user->id) {
            throw ValidationException::withMessages(['credit_card' => 'Cartão inválido.']);
        }

        $referenceMonth = Carbon::createFromFormat('Y-m', $data['reference_month'])->startOfMonth();
        $dueDate = Carbon::parse($data['due_date']);

        if ($creditCard->bills()->whereDate('reference_month', $referenceMonth)->exists()) {
            throw ValidationException::withMessages(['reference_month' => 'Já existe uma fatura para este cartão neste mês.']);
        }

        return $user->creditCardBills()->create([
            'credit_card_id' => $creditCard->id,
            'reference_month' => $referenceMonth,
            'total_amount' => Money::normalize($data['total_amount']),
            'due_date' => $dueDate,
            'status' => $dueDate->lt(today())
                ? CreditCardBillStatus::Overdue->value
                : CreditCardBillStatus::Pending->value,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function pay(User $user, CreditCardBill $bill, Account $account, ?string $paidAt = null): CreditCardBill
    {
        if ($bill->user_id !== $user->id || $account->user_id !== $user->id) {
            throw ValidationException::withMessages(['account_id' => 'Conta ou fatura inválida.']);
        }

        return DB::transaction(function () use ($user, $bill, $account, $paidAt) {
            $locked = CreditCardBill::query()->lockForUpdate()->findOrFail($bill->id);

            if ($locked->status === CreditCardBillStatus::Paid) {
                throw ValidationException::withMessages(['bill' => 'Esta fatura já foi paga.']);
            }

            if ($locked->status === CreditCardBillStatus::Cancelled) {
                throw ValidationException::withMessages(['bill' => 'Uma fatura cancelada não pode ser paga.']);
            }

            $date = $paidAt ?: now()->toDateString();
            $category = $user->categories()->where('name', 'Dívidas')->first();
            $transaction = $user->transactions()->create([
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'type' => TransactionType::Expense->value,
                'description' => 'Fatura '.$locked->creditCard->name.' — '.$locked->reference_month->format('m/Y'),
                'amount' => $locked->total_amount,
                'competence_date' => $date,
                'due_date' => $locked->due_date,
                'paid_at' => $date,
                'status' => TransactionStatus::Completed->value,
                'source_type' => 'credit_card_bill',
                'source_id' => $locked->id,
            ]);

            $locked->update([
                'transaction_id' => $transaction->id,
                'paid_at' => $date,
                'status' => CreditCardBillStatus::Paid->value,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * O vencimento e o ajuste manual valem só para esta fatura — não mexem em
     * closing_day/due_day do cartão, que continuam regendo as próximas.
     */
    public function updateOpenBill(User $user, CreditCardBill $bill, array $data): CreditCardBill
    {
        if ($bill->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'bill' => 'Somente faturas ainda não fechadas podem ser editadas.',
            ]);
        }

        // Somar as compras e regravar o total é read-modify-write: sem a
        // transação e o lock, uma compra concorrente (que passa por
        // adjustBillTotal, este sim já travado) entra entre a leitura e a
        // escrita e some do total da fatura de forma permanente — achado M3.
        // O canEdit também é reavaliado sobre a instância travada, senão uma
        // fatura paga em paralelo ainda poderia ser reescrita aqui.
        return DB::transaction(function () use ($bill, $data): CreditCardBill {
            $locked = CreditCardBill::query()->lockForUpdate()->findOrFail($bill->getKey());

            if (! $this->canEdit($locked)) {
                throw ValidationException::withMessages([
                    'bill' => 'Somente faturas ainda não fechadas podem ser editadas.',
                ]);
            }

            $registeredPurchases = bcadd('0', (string) $locked->purchases()
                ->where('status', '!=', TransactionStatus::Cancelled->value)
                ->sum('amount'), 2);

            // array_key_exists (não filled()) para distinguir "campo não veio na
            // requisição" (preserva o ajuste atual da fatura — ex.: um PATCH
            // parcial via API) de "campo veio vazio" (usuário limpou o valor de
            // propósito, zera o ajuste). O form da tela sempre envia os dois.
            if (array_key_exists('adjustment_amount', $data)) {
                $adjustmentAmount = filled($data['adjustment_amount']) ? Money::normalize($data['adjustment_amount']) : '0.00';
                if (($data['adjustment_type'] ?? 'acrescimo') === 'desconto') {
                    $adjustmentAmount = bcmul($adjustmentAmount, '-1', 2);
                }
            } else {
                $adjustmentAmount = (string) $locked->adjustment_amount;
            }

            $normalizedTotal = bcadd($registeredPurchases, $adjustmentAmount, 2);

            if (bccomp($normalizedTotal, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'adjustment_amount' => 'O desconto não pode deixar a fatura negativa.',
                ]);
            }

            $locked->update([
                'total_amount' => $normalizedTotal,
                'adjustment_amount' => $adjustmentAmount,
                'adjustment_reason' => array_key_exists('adjustment_reason', $data) ? $data['adjustment_reason'] : $locked->adjustment_reason,
                'due_date' => filled($data['due_date'] ?? null) ? Carbon::parse($data['due_date']) : $locked->due_date,
            ]);

            return $locked->refresh();
        });
    }

    public function canEdit(CreditCardBill $bill, ?CarbonInterface $today = null): bool
    {
        if (in_array($bill->status, [CreditCardBillStatus::Paid, CreditCardBillStatus::Cancelled], true)) {
            return false;
        }

        return ($today ?? today())->startOfDay()->lte($this->closingDate($bill));
    }

    public function closingDate(CreditCardBill $bill): Carbon
    {
        $card = $bill->creditCard;
        $dueDate = $bill->due_date->copy()->startOfDay();
        $closingMonth = $card->closing_day < $card->due_day
            ? $dueDate->copy()
            : $dueDate->copy()->subMonthNoOverflow();

        return $closingMonth->day(min($closingMonth->daysInMonth, $card->closing_day));
    }

    public function billForPurchase(User $user, CreditCard $creditCard, string|CarbonInterface $purchaseDate): CreditCardBill
    {
        if ($creditCard->user_id !== $user->id || ! $creditCard->active) {
            throw ValidationException::withMessages(['credit_card_id' => 'Cartão inválido ou inativo.']);
        }

        $purchase = $purchaseDate instanceof CarbonInterface
            ? Carbon::instance($purchaseDate)->startOfDay()
            : Carbon::parse($purchaseDate)->startOfDay();
        $closingDate = $purchase->copy()->day(min($purchase->daysInMonth, $creditCard->closing_day));

        if ($purchase->gt($closingDate)) {
            $closingDate->addMonthNoOverflow()->day(min($closingDate->daysInMonth, $creditCard->closing_day));
        }

        $dueDate = $closingDate->copy()->day(min($closingDate->daysInMonth, $creditCard->due_day));
        if ($dueDate->lte($closingDate)) {
            $dueDate->addMonthNoOverflow()->day(min($dueDate->daysInMonth, $creditCard->due_day));
        }

        $referenceMonth = $dueDate->copy()->startOfMonth();

        return $user->creditCardBills()->firstOrCreate(
            [
                'credit_card_id' => $creditCard->id,
                'reference_month' => $referenceMonth,
            ],
            [
                'total_amount' => '0.00',
                'due_date' => $dueDate,
                'status' => $dueDate->lt(today())
                    ? CreditCardBillStatus::Overdue->value
                    : CreditCardBillStatus::Pending->value,
                'notes' => 'Fatura criada automaticamente a partir de compras no cartão.',
            ],
        );
    }

    public function adjustBillTotal(CreditCardBill $bill, string $difference): void
    {
        $locked = CreditCardBill::query()->lockForUpdate()->findOrFail($bill->id);

        if (in_array($locked->status, [CreditCardBillStatus::Paid, CreditCardBillStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'credit_card_id' => 'A fatura correspondente já foi paga ou cancelada.',
            ]);
        }

        $updated = bcadd($locked->total_amount, $difference, 2);
        if (bccomp($updated, '0', 2) < 0) {
            throw ValidationException::withMessages(['amount' => 'O total da fatura não pode ficar negativo.']);
        }

        $locked->update(['total_amount' => $updated]);
    }

    public function refreshOverdue(User $user): void
    {
        $user->creditCardBills()
            ->where('status', CreditCardBillStatus::Pending->value)
            ->whereDate('due_date', '<', today())
            ->update(['status' => CreditCardBillStatus::Overdue->value]);
    }

    public function debtSummary(User $user): array
    {
        $this->refreshOverdue($user);

        $loanOutstanding = bcadd('0', (string) $user->debtInstallments()
            ->whereNotIn('status', [
                DebtInstallmentStatus::Paid->value,
                DebtInstallmentStatus::Cancelled->value,
            ])
            ->sum('amount'), 2);
        $cardOutstanding = bcadd('0', (string) $user->creditCardBills()
            ->whereIn('status', [
                CreditCardBillStatus::Pending->value,
                CreditCardBillStatus::Overdue->value,
            ])
            ->sum('total_amount'), 2);

        return [
            'loans' => $loanOutstanding,
            'cards' => $cardOutstanding,
            'total' => bcadd($loanOutstanding, $cardOutstanding, 2),
            'overdue_bills' => $user->creditCardBills()
                ->where('status', CreditCardBillStatus::Overdue->value)
                ->count(),
        ];
    }

    public function cardSummary(CreditCard $creditCard): array
    {
        $outstanding = (string) $creditCard->bills()
            ->whereIn('status', [
                CreditCardBillStatus::Pending->value,
                CreditCardBillStatus::Overdue->value,
            ])
            ->sum('total_amount');

        return [
            'outstanding' => $outstanding,
            'available_limit' => bcsub($creditCard->credit_limit, $outstanding, 2),
            'next_bill' => $creditCard->bills()
                ->whereIn('status', [
                    CreditCardBillStatus::Pending->value,
                    CreditCardBillStatus::Overdue->value,
                ])
                ->orderBy('due_date')
                ->first(),
        ];
    }
}
