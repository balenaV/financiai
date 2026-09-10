<?php

namespace App\Exceptions;

use App\Models\User;
use App\Services\ReauthenticationService;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A ação exige reautenticação e o usuário não tem senha utilizável — o único
 * caminho é refazer o consentimento no provedor social.
 *
 * Responde 423 (Locked), o mesmo status que o middleware password.confirm do
 * Laravel usa para requisições JSON, com a URL do re-consentimento no corpo
 * para o front redirecionar.
 */
class ReauthenticationRequiredException extends Exception
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $url = null,
    ) {
        parent::__construct('Confirme sua identidade para continuar.');
    }

    public static function for(User $user, ReauthenticationService $service): self
    {
        $provider = $service->availableProvider($user);

        return new self(
            $provider,
            $provider ? route('social.reauthenticate', $provider) : null,
        );
    }

    /**
     * Não é erro: é o fluxo normal de quem precisa confirmar identidade pelo
     * provedor. Sem isso, todo clique em "Ativar" de uma conta de login social
     * viraria uma entrada de exceção no log, escondendo problema de verdade.
     */
    public function report(): bool
    {
        return false;
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->message,
                'reauthentication' => [
                    'provider' => $this->provider,
                    'url' => $this->url,
                ],
            ], 423);
        }

        if ($this->url === null) {
            return back()->withErrors([
                'password' => 'Sua conta não tem senha definida. Use "Esqueci minha senha" para criar uma antes de continuar.',
            ]);
        }

        return redirect()->to($this->url);
    }
}
