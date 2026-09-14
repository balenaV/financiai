<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\User;
use App\Services\InvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_filters_by_operation_group(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        $service = app(InvestmentService::class);
        $service->addOperation($user, $investment, ['type' => 'contribution', 'amount' => '100.00', 'operation_date' => today()->toDateString()]);
        $service->addOperation($user, $investment, ['type' => 'withdrawal', 'amount' => '30.00', 'operation_date' => today()->toDateString()]);
        $service->addOperation($user, $investment, ['type' => 'yield', 'amount' => '5.00', 'operation_date' => today()->toDateString()]);

        $response = $this->actingAs($user)->get(route('investments.show', [$investment, 'op_type' => 'aportes']));

        $response->assertOk()->assertSee('Aportes (1)', false)->assertSee('Resgates (1)', false)->assertSee('Rendimentos (1)', false);
    }

    public function test_history_paginates_at_six_per_page(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        $service = app(InvestmentService::class);
        for ($i = 0; $i < 7; $i++) {
            $service->addOperation($user, $investment, ['type' => 'contribution', 'amount' => '10.00', 'operation_date' => today()->subDays($i)->toDateString()]);
        }

        $response = $this->actingAs($user)->get(route('investments.show', $investment));

        $response->assertOk()->assertSee('Mostrando 1–6 de 7 movimentações');
    }

    public function test_history_never_shows_another_users_operations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        $foreignInvestment = Investment::factory()->for($other)->create();
        $service = app(InvestmentService::class);
        $service->addOperation($user, $investment, ['type' => 'contribution', 'amount' => '10.00', 'operation_date' => today()->toDateString()]);
        $service->addOperation($other, $foreignInvestment, ['type' => 'contribution', 'amount' => '999999.00', 'operation_date' => today()->toDateString()]);

        $this->actingAs($user)->get(route('investments.show', $investment))
            ->assertOk()->assertDontSee('999.999');

        $this->actingAs($user)->get(route('investments.show', $foreignInvestment))->assertForbidden();
    }
}
