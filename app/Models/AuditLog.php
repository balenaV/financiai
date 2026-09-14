<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'event', 'category', 'route', 'method', 'ip_address', 'user_agent', 'metadata'])]
class AuditLog extends Model
{
    private const ROUTE_LABELS = [
        'accounts' => 'Conta',
        'categories' => 'Categoria',
        'transactions' => 'Transação',
        'credit-cards' => 'Cartão',
        'credit-card-bills' => 'Fatura',
        'debts' => 'Dívida',
        'investments' => 'Investimento',
        'budgets' => 'Orçamento',
        'goals' => 'Meta',
        'settings' => 'Preferências',
        'two-factor' => 'Autenticação de dois fatores',
        'profile' => 'Perfil',
        'password' => 'Senha',
        'notifications' => 'Notificação',
    ];

    private const EVENT_LABELS = [
        'created' => 'criada(o)',
        'updated' => 'atualizada(o)',
        'deleted' => 'excluída(o)',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /**
     * "Login com senha" já vem pronto de App\Support\SecurityAudit. As
     * entradas genéricas do middleware (created/updated/deleted) viram uma
     * frase a partir do primeiro segmento da rota — sem isso o histórico só
     * mostraria "updated" cru.
     */
    public function describe(): string
    {
        if (! in_array($this->event, array_keys(self::EVENT_LABELS), true)) {
            return $this->event;
        }

        $segment = Str::before((string) $this->route, '.');
        $resource = self::ROUTE_LABELS[$segment] ?? Str::headline($segment);

        return $resource.' '.self::EVENT_LABELS[$this->event];
    }
}
