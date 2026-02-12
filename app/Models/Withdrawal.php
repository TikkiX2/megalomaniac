<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'currency_id',
        'category_id',
        'amount',
        'withdrawal_date',
        'description',
        'notes',
        'is_recurring',
        'recurrence_frequency',
        'recurrence_day',
        'recurrence_end_date',
        'receipt_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'withdrawal_date' => 'date',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WithdrawalCategory::class, 'category_id');
    }

    // Scopes
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('withdrawal_date', [$start, $end]);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Methods
    public function convertTo(Currency $target): ?float
    {
        return $this->currency->convertTo($target, $this->amount, $this->withdrawal_date);
    }
}
