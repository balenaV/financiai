<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Reautenticação para ações sensíveis (ativar/desativar MFA, regerar códigos
 * de recuperação, encerrar outras sessões, excluir a conta).
 *
 * Existem dois modos de provar identidade, porque nem todo usuário tem senha:
 *
 *  - senha: quem se cadastrou com e-mail/senha informa "current_password" na
 *    própria requisição, como já era o padrão do app;
 *  - re-consentimento OAuth: quem entrou por Google/GitHub refaz o fluxo do
 *    provedor e, se voltar a MESMA identidade vinculada à conta logada, ganha
 *    uma janela curta de reautenticação gravada na sessão.
 *
 * O re-consentimento vale para qualquer conta com provedor vinculado, não só
 * para as OAuth-only: é o que destrava as contas antigas, criadas antes da
 * coluna has_usable_password existir, que ficariam presas na exigência de uma
 * senha que o dono nunca conheceu.
 */
class ReauthenticationService
{
    public const SESSION_KEY = 'auth.reauthenticated_at';

    public const INTENT_KEY = 'auth_reauthenticate_provider';

    public const INTENT_BACK_KEY = 'auth_reauthenticate_back';

    /** Janela curta de propósito: prova identidade para a ação em andamento, não para a sessão toda. */
    public const WINDOW_SECONDS = 300;

    /**
     * O usuário consegue cumprir uma exigência de "current_password"?
     */
    public function hasUsablePassword(User $user): bool
    {
        return $user->hasUsablePassword();
    }

    /**
     * Provedor a usar no re-consentimento. Preferimos o vínculo mais antigo,
     * que é o que criou a conta nos casos OAuth-only.
     */
    public function availableProvider(User $user): ?string
    {
        return $user->socialAccounts()->orderBy('id')->value('provider');
    }

    public function confirmedRecently(Session $session): bool
    {
        $confirmedAt = $session->get(self::SESSION_KEY);

        return is_int($confirmedAt) && (time() - $confirmedAt) < self::WINDOW_SECONDS;
    }

    /**
     * Uma janela já viva NÃO é estendida.
     *
     * Enquanto ela vale, nenhuma ação sensível pede prova nova — então toda
     * chamada aqui nesse intervalo seria uma renovação sem prova. Sem essa
     * guarda, bastava bater em POST /settings/reauthenticate com o corpo vazio
     * a cada poucos minutos para transformar os 300 segundos em acesso
     * permanente às ações sensíveis, que é exatamente o que a janela curta
     * existe para impedir.
     */
    public function markConfirmed(Session $session): void
    {
        if ($this->confirmedRecently($session)) {
            return;
        }

        $session->put(self::SESSION_KEY, time());
    }

    public function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
