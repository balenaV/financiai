<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // O desafio de dois fatores é limitado pelo usuário do desafio, não
        // pelo IP: como quem chega aqui já provou a senha, deixar o IP na
        // chave permitiria varrer o TOTP reiniciando o balde a cada troca de
        // proxy (achado A2). O IP entra só como desempate para requisições
        // sem sessão de desafio, que não deveriam existir.
        RateLimiter::for('two-factor', function (Request $request) {
            $userId = $request->session()->get('two_factor.user_id');

            return Limit::perMinute(10)->by('two-factor|'.($userId ?? $request->ip()));
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            $expiration = (int) config('auth.verification.expire', 60);

            return (new MailMessage)
                ->subject('Confirme seu e-mail — financi.ai')
                ->greeting('Olá, '.$notifiable->name.'!')
                ->line('Sua conta está quase pronta. Confirme seu endereço de e-mail para acessar o financi.ai com segurança.')
                ->action('Confirmar meu e-mail', $verificationUrl)
                ->line("Por segurança, este link expira em {$expiration} minutos.")
                ->line('Se você não criou esta conta, ignore esta mensagem. Nenhuma ação será realizada.')
                ->salutation('Até já, equipe financi.ai');
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $broker = (string) config('auth.defaults.passwords');
            $expiration = (int) config("auth.passwords.{$broker}.expire", 60);
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Redefina sua senha — financi.ai')
                ->greeting('Olá, '.$notifiable->name.'!')
                ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
                ->action('Criar nova senha', $resetUrl)
                ->line("Este link expira em {$expiration} minutos e só pode ser usado para esta conta.")
                ->line('Se você não solicitou a alteração, pode ignorar esta mensagem com segurança.')
                ->salutation('Com segurança, equipe financi.ai');
        });
    }
}
