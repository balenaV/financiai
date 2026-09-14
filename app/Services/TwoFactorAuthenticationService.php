<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    private const RECOVERY_CODES_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Gera um secret novo e o guarda em two_factor_pending_secret, sem tocar
     * no MFA em vigor.
     *
     * Antes isso escrevia direto em two_factor_secret e zerava
     * two_factor_confirmed_at junto com os códigos de recuperação — abrir o
     * modal e desistir (fechar a aba, errar o código, cair a conexão) deixava
     * a conta sem MFA e sem códigos, silenciosamente (achado M1).
     */
    public function startActivation(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill(['two_factor_pending_secret' => $secret])->save();

        return $secret;
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl('financiaí', $user->email, $secret);

        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($uri);
    }

    /** Desafio de login: valida contra o secret em vigor. */
    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_secret, $code) === true;
    }

    /** Etapa 2 da ativação: valida contra o secret ainda pendente. */
    public function verifyPendingCode(User $user, string $code): bool
    {
        if (! $user->two_factor_pending_secret) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_pending_secret, $code) === true;
    }

    /**
     * Promove o secret pendente a secret em vigor e emite os códigos de
     * recuperação em claro — a única vez que eles existem fora da forma com
     * hash. Só aqui o MFA anterior (se havia) é substituído.
     *
     * Tudo numa transação: sem ela, uma falha no meio deixaria o MFA ativo
     * com zero códigos de recuperação, ou apagaria os antigos sem emitir os
     * novos (achado B1).
     *
     * @return list<string>
     */
    public function confirm(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $user->forceFill([
                'two_factor_secret' => $user->two_factor_pending_secret,
                'two_factor_pending_secret' => null,
                'two_factor_confirmed_at' => now(),
            ])->save();

            return $this->regenerateRecoveryCodes($user);
        });
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $user->twoFactorRecoveryCodes()->delete();

            $codes = [];

            for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
                $code = Str::upper(Str::random(4).'-'.Str::random(4));
                $codes[] = $code;

                $user->twoFactorRecoveryCodes()->create(['code_hash' => Hash::make($code)]);
            }

            return $codes;
        });
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $recoveryCode = $user->twoFactorRecoveryCodes()
            ->whereNull('used_at')
            ->get()
            ->first(fn ($stored) => Hash::check($code, $stored->code_hash));

        if (! $recoveryCode) {
            return false;
        }

        $recoveryCode->update(['used_at' => now()]);

        return true;
    }

    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_pending_secret' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $user->twoFactorRecoveryCodes()->delete();
        });
    }
}
