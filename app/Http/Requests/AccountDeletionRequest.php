<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsReauthentication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountDeletionRequest extends FormRequest
{
    use ConfirmsReauthentication;

    /** Mantém o bag que a modal de encerrar conta já lê no dashboard. */
    protected $errorBag = 'userDeletion';

    /** @var array<int, string> */
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reauthenticationRules('password');
    }
}
