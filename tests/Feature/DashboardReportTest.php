<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_filters_and_calculations_render_only_the_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => '1000.00']);
        $foreign = Account::factory()->for($other)->create(['initial_balance' => '999999.00']);
        $this->createIncome($user, $account, '500.00');
        $this->createIncome($other, $foreign, '800000.00');

        $response = $this->actingAs($user)->get(route('dashboard', [
            'month' => now()->month,
            'year' => now()->year,
            'account_id' => $account->id,
        ]));

        $response->assertOk()->assertSee('Visão geral')->assertSee('1.500,00')->assertDontSee('999.999');
    }

    public function test_reports_and_csv_export_are_available(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $this->createIncome($user, $account, '250.00');

        $this->actingAs($user)->get(route('reports.index', [
            'type' => 'cash_flow',
            'start_date' => today()->startOfMonth()->toDateString(),
            'end_date' => today()->endOfMonth()->toDateString(),
        ]))->assertOk()->assertSee('Fluxo de caixa')->assertSee('Receita teste');

        $this->actingAs($user)->get(route('reports.export', [
            'start_date' => today()->startOfMonth()->toDateString(),
            'end_date' => today()->endOfMonth()->toDateString(),
        ]))->assertOk()->assertDownload();

        $this->actingAs($user)->get(route('transactions.export'))->assertOk()->assertDownload();
    }

    public function test_csv_exports_neutralize_formula_injection_in_description(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $user->categories()->where('name', 'Salário')->value('id'),
            'type' => 'income',
            'description' => '=cmd|"/c calc"!A1',
            'amount' => '250.00',
            'competence_date' => today(),
            'paid_at' => today(),
            'status' => 'completed',
        ]);

        $reportCsv = $this->actingAs($user)->get(route('reports.export', [
            'start_date' => today()->startOfMonth()->toDateString(),
            'end_date' => today()->endOfMonth()->toDateString(),
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=cmd", $reportCsv);
        $this->assertStringNotContainsString(';=cmd', $reportCsv);

        $transactionsCsv = $this->actingAs($user)->get(route('transactions.export'))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString("'=cmd", $transactionsCsv);
        $this->assertStringNotContainsString(';=cmd', $transactionsCsv);
    }

    public function test_dashboard_rejects_foreign_account_filter(): void
    {
        $user = User::factory()->create();
        $foreign = Account::factory()->for(User::factory())->create();

        $this->actingAs($user)->get(route('dashboard', ['account_id' => $foreign->id]))->assertNotFound();
    }

    public function test_report_detail_panel_lists_only_the_users_transactions_in_the_six_month_window(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $foreignAccount = Account::factory()->for($other)->create();
        $this->createIncome($user, $account, '250.00');
        $this->createIncome($other, $foreignAccount, '999.00');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('Receita teste')->assertDontSee('999,00');
    }

    public function test_report_detail_panel_opens_and_sorts_when_query_params_are_present(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $this->createIncome($user, $account, '100.00');

        $response = $this->actingAs($user)->get(route('dashboard', ['rep_sort' => 'valor', 'rep_dir' => 'asc']));

        $response->assertOk()->assertSee('report-detail__body', false)->assertDontSee('report-detail__body" hidden', false);
    }

    public function test_report_detail_panel_paginates_at_eight_per_page(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        for ($i = 0; $i < 9; $i++) {
            $this->createIncome($user, $account, (string) (10 + $i).'.00');
        }

        $response = $this->actingAs($user)->get(route('dashboard', ['rep_sort' => 'data']));

        $response->assertOk()->assertSee('Mostrando 1–8 de 9 lançamentos');
    }

    public function test_report_kpi_delta_reflects_current_month_versus_previous_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $this->createIncome($user, $account, '1000.00');
        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $user->categories()->where('name', 'Salário')->value('id'),
            'type' => 'income',
            'description' => 'Receita mês anterior',
            'amount' => '500.00',
            'competence_date' => now()->subMonthNoOverflow(),
            'paid_at' => now()->subMonthNoOverflow(),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('vs. mês anterior');
    }

    private function createIncome(User $user, Account $account, string $amount): void
    {
        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $user->categories()->where('name', 'Salário')->value('id'),
            'type' => 'income',
            'description' => 'Receita teste',
            'amount' => $amount,
            'competence_date' => today(),
            'paid_at' => today(),
            'status' => 'completed',
        ]);
    }
}
