<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_password_login_is_logged_as_acesso(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'acesso',
            'event' => 'Login com senha',
        ]);
    }

    public function test_blocked_login_attempt_is_logged_as_alerta(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear($this->throttleKeyFor($user->email));

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'alerta',
            'event' => 'Tentativa de login bloqueada',
        ]);
    }

    public function test_two_factor_challenge_success_is_logged_as_acesso(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->startActivation($user);
        $service->confirm($user);

        $this->withSession(['two_factor.user_id' => $user->id]);
        $code = app(Google2FA::class)->getCurrentOtp($user->fresh()->two_factor_secret);
        $this->post(route('two-factor.verify'), ['code' => $code]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'acesso',
            'event' => 'Login com verificação em duas etapas',
        ]);
    }

    public function test_two_factor_wrong_code_is_logged_as_alerta(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorAuthenticationService::class);
        $service->startActivation($user);
        $service->confirm($user);

        $this->withSession(['two_factor.user_id' => $user->id]);
        $this->post(route('two-factor.verify'), ['code' => '000000']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'alerta',
            'event' => 'Código de verificação incorreto',
        ]);
    }

    private function throttleKeyFor(string $email): string
    {
        return Str::transliterate(Str::lower($email).'|127.0.0.1');
    }
}
