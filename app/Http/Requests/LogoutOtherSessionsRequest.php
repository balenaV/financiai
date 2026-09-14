<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ConfirmsReauthentication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LogoutOtherSessionsRequest extends FormRequest
{
    use ConfirmsReauthentication;

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
