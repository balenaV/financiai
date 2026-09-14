<?php

namespace App\Support;

/**
 * Resumo curto "navegador · sistema" para o bloco "Atividade da conta" —
 * não precisa ser exato, só dar contexto de dispositivo sem trazer uma lib
 * de UA parsing só para isso.
 */
class UserAgentSummary
{
    private const BROWSERS = ['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'CriOS' => 'Chrome', 'Firefox' => 'Firefox', 'FxiOS' => 'Firefox', 'Safari' => 'Safari'];

    private const SYSTEMS = ['Windows' => 'Windows', 'iPhone' => 'iOS', 'iPad' => 'iOS', 'Mac OS X' => 'macOS', 'Android' => 'Android', 'Linux' => 'Linux'];

    public static function describe(?string $userAgent): string
    {
        if (blank($userAgent)) {
            return 'Dispositivo desconhecido';
        }

        $browser = null;
        foreach (self::BROWSERS as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $browser = $label;
                break;
            }
        }

        $system = null;
        foreach (self::SYSTEMS as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $system = $label;
                break;
            }
        }

        return implode(' · ', array_filter([$browser, $system])) ?: 'Navegador desconhecido';
    }
}
