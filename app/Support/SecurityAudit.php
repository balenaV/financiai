<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pontos de login/MFA não passam pelo middleware AuditUserAction (rodam
 * antes do usuário estar autenticado, ou fora das rotas que o carregam) —
 * é o que alimenta o bloco "Atividade da conta" com acessos e bloqueios.
 */
class SecurityAudit
{
    public static function log(User $user, string $category, string $event, ?Request $request = null): void
    {
        $request ??= request();

        AuditLog::create([
            'user_id' => $user->id,
            'event' => $event,
            'category' => $category,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'metadata' => ['status' => 200],
        ]);
    }
}
