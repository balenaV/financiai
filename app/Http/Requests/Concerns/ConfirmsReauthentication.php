<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\ReauthenticationRequiredException;
use App\Services\ReauthenticationService;

/**
 * Aplica a reautenticação genérica num FormRequest.
 *
 * Ordem de decisão:
 *  1. re-consentimento OAuth recente na sessão  -> libera, sem pedir nada;
 *  2. usuário com senha utilizável              -> exige o campo de senha;
 *  3. usuário sem senha utilizável              -> 423 com a URL do provedor.
 */
trait ConfirmsReauthentication
{
    /**
     * @return array<string, array<int, string>>
     *
     * @throws ReauthenticationRequiredException
     */
    protected function reauthenticationRules(string $field = 'current_password'): array
    {
        $service = app(ReauthenticationService::class);

        if ($service->confirmedRecently($this->session())) {
            return [];
        }

        if (! $service->hasUsablePassword($this->user())) {
            throw ReauthenticationRequiredException::for($this->user(), $service);
        }

        return [$field => ['required', 'current_password']];
    }
}
