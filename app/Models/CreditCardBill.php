<?php

namespace App\Models;

use App\Enums\CreditCardBillStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['credit_card_id', 'transaction_id', 'reference_month', 'total_amount', 'adjustment_amount', 'adjustment_reason', 'due_date', 'paid_at', 'status', 'notes'])]
class CreditCardBill extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'total_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
            'status' => CreditCardBillStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('payment_channel', 'credit_card');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
