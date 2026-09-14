<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_search_filters_by_description(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '0.00']);
        $this->createTransaction($user, $account, 'income', '100.00', 'Salário de agosto');
        $this->createTransaction($user, $account, 'expense', '20.00', 'Mercado da esquina');

        $response = $this->actingAs($user)->get(route('accounts.show', [$account, 'stmt_search' => 'mercado']));

        $response->assertOk()->assertSee('Mercado da esquina')->assertDontSee('Salário de agosto');
    }

    public function test_statement_type_filter_shows_only_entradas_or_saidas(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '0.00']);
        $this->createTransaction($user, $account, 'income', '100.00', 'Salário de agosto');
        $this->createTransaction($user, $account, 'expense', '20.00', 'Mercado da esquina');

        $response = $this->actingAs($user)->get(route('accounts.show', [$account, 'stmt_type' => 'entradas']));

        $response->assertOk()->assertSee('Salário de agosto')->assertDontSee('Mercado da esquina');
    }

    public function test_statement_paginates_at_eight_per_page(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '0.00']);
        for ($i = 0; $i < 9; $i++) {
            $this->createTransaction($user, $account, 'income', '10.00', 'Lançamento '.$i);
        }

        $response = $this->actingAs($user)->get(route('accounts.show', $account));

        $response->assertOk()->assertSee('Mostrando 1–8 de 9 movimentações');
    }

    public function test_running_balance_follows_full_chronological_order_regardless_of_filter(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '0.00']);
        $this->createTransaction($user, $account, 'income', '100.00', 'Salário', '2026-08-01');
        $this->createTransaction($user, $account, 'expense', '30.00', 'Mercado', '2026-08-05');
        $this->createTransaction($user, $account, 'income', '50.00', 'Bônus', '2026-08-10');

        $response = $this->actingAs($user)->get(route('accounts.show', [$account, 'stmt_type' => 'entradas']));

        // Mesmo filtrando só entradas, "Bônus" (o mais recente) precisa mostrar
        // o saldo acumulado real (100 - 30 + 50 = 120), não 100 + 50.
        $response->assertOk()->assertSeeInOrder(['Bônus', '120,00']);
    }

    private function createTransaction(User $user, Account $account, string $type, string $amount, string $description, ?string $date = null): void
    {
        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $user->categories()->where('type', $type === 'income' ? 'income' : 'expense')->value('id'),
            'type' => $type,
            'description' => $description,
            'amount' => $amount,
            'competence_date' => $date ?? today(),
            'paid_at' => $date ?? today(),
            'status' => 'completed',
        ]);
    }
}
