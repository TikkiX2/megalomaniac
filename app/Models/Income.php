<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    protected $fillable = [
        'user_id',
        'income_source_id',
        'currency_id',
        'amount',
        'received_date',
        'description',
        'is_recurring',
        'recurrence_day',
        'recurrence_end_date',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_date' => 'date',
            'is_recurring' => 'boolean',
            'recurrence_end_date' => 'date',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incomeSource(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Scopes
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('received_date', [$start, $end]);
    }

    // Methods
    public function convertTo(Currency $target): ?float
    {
        return $this->currency->convertTo($target, $this->amount, $this->received_date);
    }
}
