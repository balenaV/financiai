<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_access_is_offered_only_on_the_login_screen(): void
    {
        $googleUrl = route('social.redirect', ['provider' => 'google']);
        $githubUrl = route('social.redirect', ['provider' => 'github']);

        $this->get('/login')
            ->assertOk()
            ->assertSee($googleUrl)
            ->assertSee($githubUrl);

        // Login and register share one page with a client-side tab switch, so both
        // forms are always present in the markup. The business rule that matters is
        // that social auth can never complete the *registration* form itself.
        $registerResponse = $this->get('/register')->assertOk();
        $registroForm = Str::before(Str::after($registerResponse->getContent(), 'auth-form--registro'), '</form>');

        $this->assertStringNotContainsString($googleUrl, $registroForm);
        $this->assertStringNotContainsString($githubUrl, $registroForm);
    }

    public function test_unsupported_social_provider_returns_not_found(): void
    {
        $this->get('/auth/unknown/redirect')->assertNotFound();
    }

    public function test_unconfigured_provider_returns_a_friendly_error(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->get('/auth/google/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    public function test_google_callback_creates_a_verified_user_and_social_account(): void
    {
        $this->configureProvider('google');
        $this->fakeProvider('google', [
            'id' => 'google-user-123',
            'name' => 'Victor Balena',
            'email' => 'victor@example.com',
            'avatar' => 'https://example.com/avatar.png',
        ]);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'victor@example.com',
            'name' => 'Victor Balena',
        ]);
        $this->assertNotNull(User::where('email', 'victor@example.com')->firstOrFail()->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);
        $response->assertSessionHas('success', 'Bem-vindo(a) ao financiaí! Sua conta foi criada com sucesso.');

        $this->get(route('dashboard'))->assertSee('Bem-vindo(a) ao financiaí! Sua conta foi criada com sucesso.');
    }

    public function test_returning_oauth_user_does_not_see_the_welcome_message(): void
    {
        $user = User::factory()->create(['email' => 'victor@example.com']);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);

        $this->configureProvider('google');
        $this->fakeProvider('google', [
            'id' => 'google-user-123',
            'name' => 'Victor Balena',
            'email' => 'victor@example.com',
        ]);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $response->assertSessionMissing('success');
    }

    public function test_linking_an_existing_user_by_email_does_not_show_the_welcome_message(): void
    {
        $user = User::factory()->create(['email' => 'victor@example.com']);

        $this->configureProvider('github');
        $this->fakeProvider('github', [
            'id' => 'github-user-456',
            'name' => 'Victor',
            'email' => 'VICTOR@example.com',
        ]);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $response->assertSessionMissing('success');
    }

    public function test_github_callback_links_an_existing_verified_user_without_duplicating_it(): void
    {
        $user = User::factory()->create(['email' => 'victor@example.com']);

        $this->configureProvider('github');
        $this->fakeProvider('github', [
            'id' => 'github-user-456',
            'name' => 'Victor',
            'email' => 'VICTOR@example.com',
        ]);

        $this->get('/auth/github/callback')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => 'github-user-456',
        ]);
    }

    public function test_social_login_does_not_hijack_an_unverified_local_account_with_the_same_email(): void
    {
        $victim = User::factory()->unverified()->create(['email' => 'victor@example.com']);

        $this->configureProvider('github');
        $this->fakeProvider('github', [
            'id' => 'github-user-456',
            'name' => 'Victor',
            'email' => 'VICTOR@example.com',
        ]);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('login'))->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertNull($victim->fresh()->email_verified_at);
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $victim->id,
            'provider' => 'github',
        ]);
        $this->assertSame(1, User::count());
    }

    public function test_social_login_does_not_force_verify_an_email_changed_after_linking(): void
    {
        $user = User::factory()->create(['email' => 'victor@example.com']);
        $user->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);

        // Simula uma troca de e-mail feita depois do vínculo (ProfileController::update
        // zera email_verified_at), ainda sem o usuário ter provado posse do novo endereço.
        $user->forceFill(['email' => 'novo-email@example.com', 'email_verified_at' => null])->save();

        $this->configureProvider('google');
        $this->fakeProvider('google', [
            'id' => 'google-user-123',
            'name' => 'Victor Balena',
            'email' => 'victor@example.com',
        ]);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_victim_cannot_be_taken_over_after_attacker_changes_email_to_hers(): void
    {
        $attacker = User::factory()->create(['email' => 'atacante@example.com']);
        $attacker->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-attacker-id',
        ]);

        // Simula o atacante trocando o e-mail da própria conta para o da vítima em
        // /profile (ProfileController zera email_verified_at nessa troca).
        $attacker->forceFill(['email' => 'vitima@example.com', 'email_verified_at' => null])->save();

        // A vítima, dona de verdade de vitima@example.com, tenta entrar com o
        // provedor dela pela primeira vez.
        $this->configureProvider('github');
        $this->fakeProvider('github', [
            'id' => 'github-victim-id',
            'name' => 'Vítima',
            'email' => 'vitima@example.com',
        ]);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('login'))->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $attacker->id,
            'provider' => 'github',
        ]);
        $this->assertNull($attacker->fresh()->email_verified_at);
        $this->assertSame(1, User::count());
    }

    public function test_social_registration_respects_the_registration_feature_flag(): void
    {
        config(['features.registration' => false]);
        $this->configureProvider('google');
        $this->fakeProvider('google', [
            'id' => 'new-google-user',
            'name' => 'Novo usuário',
            'email' => 'novo@example.com',
        ]);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
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
     * @param  array{id: string, name?: string, email: string, avatar?: string}  $attributes
     */
    private function fakeProvider(string $provider, array $attributes): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $attributes['id'],
            'name' => $attributes['name'] ?? null,
            'email' => $attributes['email'],
            'avatar' => $attributes['avatar'] ?? null,
        ]);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->once()->with($provider)->andReturn($driver);
    }
}
