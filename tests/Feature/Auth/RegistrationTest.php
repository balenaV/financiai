<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('registered', true);
        $this->assertDatabaseHas('user_settings', ['user_id' => auth()->id()]);
        Notification::assertSentTo(User::whereEmail('test@example.com')->firstOrFail(), VerifyEmail::class);
        $this->assertDatabaseHas('categories', ['user_id' => auth()->id(), 'name' => 'Salário']);
    }

    public function test_registration_redirects_to_verify_email_screen_with_friendly_message(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'confirma@example.com',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'terms' => '1',
        ]);

        $response = $this->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('Conta criada com sucesso!');
        $response->assertSee('confirma@example.com');
    }

    public function test_registration_requires_accepting_the_terms(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'no-terms@example.com',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
        ]);

        $response->assertSessionHasErrorsIn('registro', ['terms']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'no-terms@example.com']);
    }

    public function test_registration_is_blocked_when_turnstile_verification_fails(): void
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'robot@example.com',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'terms' => '1',
            'cf-turnstile-response' => 'invalid-token',
        ]);

        $response->assertSessionHasErrorsIn('registro', ['cf-turnstile-response']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'robot@example.com']);
    }

    public function test_registration_succeeds_when_turnstile_verification_passes(): void
    {
        config(['services.turnstile.secret' => 'test-secret']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'verified-human@example.com',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
            'terms' => '1',
            'cf-turnstile-response' => 'valid-token',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_registration_can_be_disabled(): void
    {
        config(['features.registration' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'SenhaForte@123',
            'password_confirmation' => 'SenhaForte@123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }
}
