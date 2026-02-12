<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyExchange extends Model
{
    protected $fillable = [
        'user_id',
        'from_currency_id',
        'to_currency_id',
        'from_amount',
        'to_amount',
        'exchange_rate',
        'exchange_date',
        'notes',
        'withdrawal_id',
        'income_id',
        'to_reserve_id',
        'reserve_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'from_amount' => 'decimal:2',
            'to_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'exchange_date' => 'date',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    public function toReserve(): BelongsTo
    {
        return $this->belongsTo(SavingsReserve::class, 'to_reserve_id');
    }

    public function reserveTransaction(): BelongsTo
    {
        return $this->belongsTo(ReserveTransaction::class);
    }
}
