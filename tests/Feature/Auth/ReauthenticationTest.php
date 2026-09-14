<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\ReauthenticationService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Reautenticação genérica para ações sensíveis: quem tem senha informa a
 * senha; quem entrou por login social e nunca definiu uma refaz o
 * consentimento no provedor. Antes, o segundo grupo simplesmente não
 * conseguia desativar o MFA, regerar códigos, encerrar sessões nem excluir a
 * própria conta.
 */
class ReauthenticationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- senha

    public function test_user_with_a_password_activates_two_factor_the_usual_way(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('two-factor.enable'), ['current_password' => 'password'])
            ->assertOk();
    }

    public function test_user_with_a_password_is_rejected_when_the_password_is_wrong(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('two-factor.enable'), ['current_password' => 'errada'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->two_factor_pending_secret);
    }

    // ----------------------------------------------------------- OAuth-only

    public function test_oauth_only_user_cannot_activate_two_factor_without_reauthenticating(): void
    {
        $user = $this->oauthOnlyUser();

        $response = $this->actingAs($user)->postJson(route('two-factor.enable'));

        // 423 Locked com o caminho do re-consentimento: não é erro de senha,
        // é "prove quem você é por outro meio".
        $response->assertStatus(423)
            ->assertJsonPath('reauthentication.provider', 'google')
            ->assertJsonPath('reauthentication.url', route('social.reauthenticate', 'google'));

        $this->assertNull($user->fresh()->two_factor_pending_secret);
    }

    public function test_oauth_only_user_activates_two_factor_after_reauthenticating(): void
    {
        $user = $this->oauthOnlyUser();

        $this->actingAs($user)
            ->withSession([ReauthenticationService::SESSION_KEY => time()])
            ->postJson(route('two-factor.enable'))
            ->assertOk()
            ->assertJsonStructure(['key', 'qr']);

        $this->assertNotNull($user->fresh()->two_factor_pending_secret);
    }

    public function test_a_stale_reauthentication_no_longer_counts(): void
    {
        $user = $this->oauthOnlyUser();

        $this->actingAs($user)
            ->withSession([
                ReauthenticationService::SESSION_KEY => time() - ReauthenticationService::WINDOW_SECONDS - 1,
            ])
            ->postJson(route('two-factor.enable'))
            ->assertStatus(423);
    }

    // --------------------------------- fluxo real de re-consentimento OAuth

    public function test_returning_from_the_provider_with_the_same_identity_grants_reauthentication(): void
    {
        $user = $this->oauthOnlyUser('google', 'provider-123');
        $this->configureProvider('google');
        $this->fakeProvider('google', ['id' => 'provider-123', 'email' => $user->email]);

        $this->actingAs($user)
            ->withSession([ReauthenticationService::INTENT_KEY => 'google'])
            ->get(route('social.callback', 'google'))
            ->assertRedirect();

        $this->assertIsInt(session(ReauthenticationService::SESSION_KEY));
    }

    /**
     * O ponto que sustenta o mecanismo todo: se qualquer conta do provedor
     * servisse, uma sessão sequestrada se reautenticaria com a conta do
     * próprio atacante.
     */
    public function test_returning_from_the_provider_with_a_different_identity_is_refused(): void
    {
        $user = $this->oauthOnlyUser('google', 'provider-123');
        $this->configureProvider('google');
        $this->fakeProvider('google', ['id' => 'conta-do-atacante', 'email' => 'atacante@example.com']);

        $this->actingAs($user)
            ->withSession([ReauthenticationService::INTENT_KEY => 'google'])
            ->get(route('social.callback', 'google'))
            ->assertSessionHasErrors('social');

        $this->assertNull(session(ReauthenticationService::SESSION_KEY));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'category' => 'alerta',
        ]);
    }

    public function test_reauthentication_route_rejects_a_provider_the_account_does_not_use(): void
    {
        $user = $this->oauthOnlyUser('google', 'provider-123');
        $this->configureProvider('github');

        $this->actingAs($user)
            ->get(route('social.reauthenticate', 'github'))
            ->assertNotFound();
    }

    // ------------------------- fluxos que antes eram intransponíveis no OAuth

    public function test_oauth_only_user_can_now_disable_two_factor_after_reauthenticating(): void
    {
        $user = $this->oauthOnlyUser();
        $service = app(TwoFactorAuthenticationService::class);
        $service->startActivation($user);
        $service->confirm($user->refresh());

        $this->actingAs($user->fresh())
            ->withSession([ReauthenticationService::SESSION_KEY => time()])
            ->deleteJson(route('two-factor.disable'))
            ->assertOk();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_oauth_only_user_can_now_end_other_sessions_after_reauthenticating(): void
    {
        $user = $this->oauthOnlyUser();

        $this->actingAs($user)
            ->withSession([ReauthenticationService::SESSION_KEY => time()])
            ->patch(route('profile.logout-other-sessions'))
            ->assertRedirect();
    }

    public function test_oauth_only_user_cannot_delete_the_account_without_reauthenticating(): void
    {
        $user = $this->oauthOnlyUser();

        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertRedirect(route('social.reauthenticate', 'google'));

        $this->assertNotNull($user->fresh());
    }

    /**
     * A janela não pode ser esticada sem prova nova: enquanto ela vale, nenhuma
     * ação sensível pede senha, então renovar de graça transformaria 5 minutos
     * em acesso permanente para quem roubou a sessão.
     */
    public function test_an_active_window_is_not_extended_without_new_proof(): void
    {
        $service = app(ReauthenticationService::class);
        $session = app('session.store');

        $service->markConfirmed($session);
        $original = $session->get(ReauthenticationService::SESSION_KEY);

        // Volta o relógio interno para simular tempo passando dentro da janela.
        $session->put(ReauthenticationService::SESSION_KEY, $original - 120);
        $service->markConfirmed($session);

        $this->assertSame(
            $original - 120,
            $session->get(ReauthenticationService::SESSION_KEY),
            'a janela viva não deveria ter sido renovada',
        );
    }

    public function test_the_reauthentication_endpoint_does_not_renew_a_live_window(): void
    {
        $user = User::factory()->create();
        $antigo = time() - 200;

        $this->actingAs($user)
            ->withSession([ReauthenticationService::SESSION_KEY => $antigo])
            ->postJson(route('reauthenticate'))
            ->assertOk();

        $this->assertSame($antigo, session(ReauthenticationService::SESSION_KEY));
    }

    /**
     * Um secret pendente abandonado não pode continuar confirmável para sempre:
     * quem viu o QR uma vez e obtiver uma sessão depois trocaria o segundo
     * fator sem provar identidade nenhuma.
     */
    public function test_confirming_activation_after_the_window_expired_is_refused(): void
    {
        $user = User::factory()->create();
        $secret = app(TwoFactorAuthenticationService::class)->startActivation($user);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->withSession([
                ReauthenticationService::SESSION_KEY => time() - ReauthenticationService::WINDOW_SECONDS - 1,
            ])
            ->postJson(route('two-factor.confirm'), ['code' => $code])
            ->assertStatus(422);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    // ------------------------------------------------------------- helpers

    private function oauthOnlyUser(string $provider = 'google', string $providerUserId = 'provider-123'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['has_usable_password' => false])->save();
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);

        return $user->fresh();
    }

    private function configureProvider(string $provider): void
    {
        config([
            "services.{$provider}.client_id" => 'client-id',
            "services.{$provider}.client_secret" => 'client-secret',
            "services.{$provider}.redirect" => "http://localhost/auth/{$provider}/callback",
        ]);
    }

    /**
     * @param  array{id: string, name?: string, email: string}  $attributes
     */
    private function fakeProvider(string $provider, array $attributes): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $attributes['id'],
            'name' => $attributes['name'] ?? null,
            'email' => $attributes['email'],
            'avatar' => null,
        ]);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with($provider)->andReturn($driver);
    }
}
