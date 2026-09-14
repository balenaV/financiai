<?php

namespace Tests\Feature;

use App\Enums\CategoryType;
use App\Enums\DebtStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\CreditCardService;
use App\Services\DashboardService;
use App\Services\DebtService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_installments_preserve_total_cents_and_end_of_month_dates(): void
    {
        $user = User::factory()->create();
        $debt = app(DebtService::class)->create($user, [
            'name' => 'Empréstimo',
            'creditor' => 'Banco',
            'kind' => 'loan',
            'original_amount' => '1000.01',
            'expected_total_amount' => '1123.47',
            'interest_rate' => '12.3456',
            'started_at' => '2026-01-01',
            'first_due_date' => '2026-01-31',
            'installment_count' => 7,
            'status' => DebtStatus::Active->value,
        ]);

        $this->assertSame(
            '1123.47',
            $debt->installments->reduce(fn ($total, $installment) => bcadd($total, $installment->amount, 2), '0.00'),
        );
        $this->assertSame(
            ['160.49', '160.49', '160.49', '160.49', '160.49', '160.49', '160.53'],
            $debt->installments->pluck('amount')->all(),
        );
        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31'],
            $debt->installments->pluck('due_date')->map->format('Y-m-d')->all(),
        );
        $this->assertSame('1123.47', app(DebtService::class)->summary($debt)['remaining']);
    }

    public function test_card_purchases_are_allocated_by_closing_date_without_changing_account_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '0.00']);
        $card = CreditCard::factory()->for($user)->create([
            'closing_day' => 10,
            'due_day' => 20,
        ]);
        $service = app(TransactionService::class);

        $first = $service->create($user, $this->cardPurchase($card, '120.00', '2026-07-10'))->first();
        $second = $service->create($user, $this->cardPurchase($card, '80.00', '2026-07-11'))->first();

        $this->assertSame('2026-07-01', $first->creditCardBill->reference_month->format('Y-m-d'));
        $this->assertSame('2026-07-20', $first->creditCardBill->due_date->format('Y-m-d'));
        $this->assertSame('120.00', $first->creditCardBill->total_amount);
        $this->assertSame('2026-08-01', $second->creditCardBill->reference_month->format('Y-m-d'));
        $this->assertSame('2026-08-20', $second->creditCardBill->due_date->format('Y-m-d'));
        $this->assertSame('80.00', $second->creditCardBill->total_amount);
        $this->assertNull($first->account_id);
        $this->assertSame('0.00', app(AccountBalanceService::class)->current($account));
        $this->assertSame('200.00', app(CreditCardService::class)->debtSummary($user)['cards']);
    }

    public function test_installment_card_purchase_distributes_cents_across_monthly_bills(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create([
            'closing_day' => 10,
            'due_day' => 20,
        ]);

        $transactions = app(TransactionService::class)->create($user, [
            ...$this->cardPurchase($card, '100.00', '2026-07-11'),
            'payment_mode' => 'installment',
            'installment_count' => 3,
            'first_installment_date' => '2026-07-11',
        ]);

        $this->assertSame(['33.33', '33.33', '33.34'], $transactions->pluck('amount')->all());
        $this->assertSame(
            ['2026-08-01', '2026-09-01', '2026-10-01'],
            $transactions->map(fn ($transaction) => $transaction->creditCardBill->reference_month->format('Y-m-d'))->all(),
        );
        $this->assertSame('100.00', app(CreditCardService::class)->debtSummary($user)['cards']);
    }

    public function test_card_purchase_can_be_created_from_transaction_form_and_updates_its_bill(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create([
            'closing_day' => 10,
            'due_day' => 20,
        ]);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

        $this->actingAs($user)->post(route('transactions.store'), [
            ...$this->cardPurchase($card, '75,90', '2026-07-11'),
            'category_id' => $category->id,
        ])->assertRedirect(route('dashboard').'#transacoes');

        $transaction = $user->transactions()->sole();
        $this->assertSame('credit_card', $transaction->payment_channel);
        $this->assertNull($transaction->account_id);
        $this->assertSame('75.90', $transaction->amount);
        $this->assertSame('75.90', $transaction->creditCardBill->total_amount);
        $this->assertSame('2026-08-20', $transaction->due_date->format('Y-m-d'));

        $this->actingAs($user)->delete(route('transactions.destroy', $transaction))->assertRedirect();
        $this->assertSame('0.00', $transaction->creditCardBill->refresh()->total_amount);
    }

    public function test_editing_card_purchase_moves_value_between_the_correct_bills(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create([
            'closing_day' => 10,
            'due_day' => 20,
        ]);
        $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
        $transaction = app(TransactionService::class)
            ->create($user, [
                ...$this->cardPurchase($card, '120.00', '2026-07-10'),
                'category_id' => $category->id,
            ])
            ->sole();
        $originalBill = $transaction->creditCardBill;

        $this->actingAs($user)->put(route('transactions.update', $transaction), [
            ...$this->cardPurchase($card, '150.00', '2026-07-11'),
            'category_id' => $category->id,
        ])->assertRedirect(route('dashboard').'#transacoes');

        $transaction->refresh();
        $this->assertSame('0.00', $originalBill->refresh()->total_amount);
        $this->assertSame('2026-08-01', $transaction->creditCardBill->reference_month->format('Y-m-d'));
        $this->assertSame('150.00', $transaction->creditCardBill->total_amount);
        $this->assertSame('150.00', app(CreditCardService::class)->debtSummary($user)['cards']);
    }

    public function test_cancelling_card_purchase_reduces_bill_only_once(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create();
        $transaction = app(TransactionService::class)
            ->create($user, $this->cardPurchase($card, '44.90', '2026-08-01'))
            ->sole();
        $bill = $transaction->creditCardBill;

        $this->actingAs($user)->patch(route('transactions.cancel', $transaction))->assertRedirect();
        $this->assertSame('0.00', $bill->refresh()->total_amount);

        $this->actingAs($user)->patch(route('transactions.cancel', $transaction))->assertRedirect();
        $this->assertSame('0.00', $bill->refresh()->total_amount);
    }

    public function test_paying_card_bill_changes_balance_without_duplicating_dashboard_expense(): void
    {
        $this->travelTo('2026-07-01');
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '1000.00']);
        $card = CreditCard::factory()->for($user)->create([
            'closing_day' => 10,
            'due_day' => 20,
        ]);
        $purchase = app(TransactionService::class)
            ->create($user, $this->cardPurchase($card, '100.00', '2026-07-01'))
            ->sole();

        app(CreditCardService::class)->pay($user, $purchase->creditCardBill, $account, '2026-07-20');
        $dashboard = app(DashboardService::class)->build($user, ['month' => 7, 'year' => 2026]);

        $this->assertSame('900.00', app(AccountBalanceService::class)->current($account));
        $this->assertSame('100.00', bcadd('0', (string) $dashboard['summary']['expense'], 2));
        $this->assertSame('0.00', app(CreditCardService::class)->debtSummary($user)['cards']);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_only_an_open_bill_can_be_edited(): void
    {
        $this->travelTo('2026-07-23');
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create([
            'closing_day' => 25,
            'due_day' => 30,
        ]);
        $service = app(CreditCardService::class);
        $openBill = $service->createBill($user, $card, [
            'reference_month' => '2026-07',
            'total_amount' => '100.00',
            'due_date' => '2026-07-30',
        ]);
        $closedBill = $service->createBill($user, $card, [
            'reference_month' => '2026-06',
            'total_amount' => '90.00',
            'due_date' => '2026-06-30',
        ]);

        $this->actingAs($user)
            ->patch(route('credit-card-bills.update', $openBill), [
                'due_date' => '2026-08-05',
                'adjustment_type' => 'acrescimo',
                'adjustment_amount' => '45,67',
                'adjustment_reason' => 'Estorno de compra cancelada',
            ])
            ->assertRedirect(route('dashboard').'#cartoes');

        $openBill->refresh();
        $this->assertSame('45.67', $openBill->total_amount);
        $this->assertSame('45.67', $openBill->adjustment_amount);
        $this->assertSame('2026-08-05', $openBill->due_date->toDateString());

        $this->actingAs($user)
            ->patch(route('credit-card-bills.update', $closedBill), [
                'adjustment_type' => 'acrescimo',
                'adjustment_amount' => '10,00',
                'adjustment_reason' => 'Tentativa em fatura fechada',
            ])
            ->assertSessionHasErrors('bill');
    }

    /**
     * Achado de segurança: um PATCH que omite adjustment_amount (ex.: uma
     * chamada de API parcial, fora do form da tela) não pode zerar
     * silenciosamente um ajuste já salvo — só quem manda o campo vazio de
     * propósito é que limpa o ajuste.
     */
    public function test_updating_only_the_due_date_preserves_the_existing_adjustment(): void
    {
        $this->travelTo('2026-07-23');
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['closing_day' => 25, 'due_day' => 30]);
        $bill = app(CreditCardService::class)->createBill($user, $card, [
            'reference_month' => '2026-07',
            'total_amount' => '100.00',
            'due_date' => '2026-07-30',
        ]);

        $this->actingAs($user)->patch(route('credit-card-bills.update', $bill), [
            'adjustment_type' => 'acrescimo',
            'adjustment_amount' => '20,00',
            'adjustment_reason' => 'Compra atrasada',
        ]);
        $this->assertSame('20.00', $bill->refresh()->adjustment_amount);

        // Segunda edição manda só o vencimento — sem adjustment_amount/reason.
        $this->actingAs($user)->patch(route('credit-card-bills.update', $bill), [
            'due_date' => '2026-08-10',
        ]);

        $bill->refresh();
        $this->assertSame('2026-08-10', $bill->due_date->toDateString());
        $this->assertSame('20.00', $bill->adjustment_amount, 'o ajuste anterior não deveria ter sido zerado');
        $this->assertSame('Compra atrasada', $bill->adjustment_reason);
    }

    private function cardPurchase(CreditCard $card, string $amount, string $date): array
    {
        return [
            'payment_channel' => 'credit_card',
            'credit_card_id' => $card->id,
            'category_id' => null,
            'type' => TransactionType::Expense->value,
            'description' => 'Compra no cartão',
            'amount' => $amount,
            'competence_date' => $date,
            'status' => TransactionStatus::Completed->value,
            'payment_mode' => 'single',
            'recurrence_count' => 1,
        ];
    }
}
