<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsReauthentication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reautenticação exigida tanto para desativar o MFA quanto para gerar novos
 * códigos de recuperação. Passou a aceitar re-consentimento OAuth além da
 * senha, o que destrava esses dois fluxos para contas criadas por login
 * social — que antes não tinham como cumprir a exigência de current_password.
 */
class TwoFactorPasswordConfirmationRequest extends FormRequest
{
    use ConfirmsReauthentication;

    /** @var array<int, string> */
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reauthenticationRules();
    }
}
