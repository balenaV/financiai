<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeRequest extends FormRequest
{
    protected $errorBag = 'mfa';

    /**
     * "code" também carrega códigos de recuperação (segredo de uso único,
     * de vida longa) quando recovery=1 — sem isso, um erro de validação
     * grava o valor digitado na sessão e o HTML de volta o re-exibe.
     *
     * @var list<string>
     */
    protected $dontFlash = ['code'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'recovery' => ['nullable', 'boolean'],
        ];
    }

    public function pendingUser(): ?User
    {
        $userId = $this->session()->get('two_factor.user_id');

        return $userId ? User::find($userId) : null;
    }

    /**
     * @throws ValidationException
     */
    public function verify(TwoFactorAuthenticationService $service): User
    {
        $user = $this->pendingUser();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'Sua sessão de verificação expirou. Faça login novamente.',
            ])->errorBag('mfa');
        }

        $this->ensureIsNotRateLimited($user);

        $code = trim((string) $this->string('code'));
        $verified = $this->boolean('recovery')
            ? $service->verifyRecoveryCode($user, $code)
            : $service->verifyCode($user, $code);

        if (! $verified) {
            RateLimiter::hit($this->throttleKey());
            SecurityAudit::log($user, 'alerta', 'Código de verificação incorreto', $this);

            throw ValidationException::withMessages([
                'code' => 'Código incorreto ou expirado. Tente novamente.',
            ])->errorBag('mfa');
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(User $user): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        SecurityAudit::log($user, 'alerta', 'Tentativa de login bloqueada (dois fatores)', $this);

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ])->errorBag('mfa');
    }

    /**
     * Chaveado só pelo usuário do desafio, sem o IP: com o IP na chave, quem
     * já tem a senha reinicia o balde a cada troca de IP e consegue varrer o
     * espaço do TOTP com um pool de proxies (achado A2). Mesmo critério que o
     * Fortify usa (Limit::perMinute(5)->by(session('login.id'))).
     */
    private function throttleKey(): string
    {
        return 'two-factor|'.$this->session()->get('two_factor.user_id');
    }
}
