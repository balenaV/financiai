<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsReauthentication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ativar o MFA é ação sensível mesmo quando não havia MFA antes.
 *
 * Sem reautenticação aqui, uma sessão sequestrada bastava para cadastrar um
 * secret novo no autenticador do atacante; como a confirmação encerra as
 * demais sessões e rotaciona o remember_token, o dono legítimo era expulso e
 * ficava sem como voltar — a senha dele continuava válida, mas o segundo
 * fator passava a ser de outra pessoa (achado A1).
 */
class TwoFactorEnableRequest extends FormRequest
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
