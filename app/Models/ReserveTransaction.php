<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReserveTransaction extends Model
{
    protected $fillable = [
        'reserve_id',
        'currency_id',
        'amount',
        'transaction_type',
        'transaction_date',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    // Relationships
    public function reserve(): BelongsTo
    {
        return $this->belongsTo(SavingsReserve::class, 'reserve_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
