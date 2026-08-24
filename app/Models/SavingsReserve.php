<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsReserve extends Model
{
    protected $fillable = [
        'user_id',
        'currency_id',
        'name',
        'description',
        'goal_amount',
        'current_amount',
        'target_date',
        'color',
        'icon',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'target_date' => 'date',
            'is_active' => 'boolean',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(ReserveTransaction::class, 'reserve_id')->latest('transaction_date');
    }

    // Methods
    public function progress(): float
    {
        if (! $this->goal_amount || $this->goal_amount <= 0) {
            return 0;
        }

        return min(100, round(($this->current_amount / $this->goal_amount) * 100, 1));
    }

    public function deposit(float $amount, string $date, ?string $description = null)
    {
        $this->transactions()->create([
            'currency_id' => $this->currency_id,
            'amount' => $amount,
            'transaction_type' => 'deposit',
            'transaction_date' => $date,
            'description' => $description ?? 'Deposit',
        ]);

        $this->increment('current_amount', $amount);
    }

    public function withdraw(float $amount, string $date, ?string $description = null)
    {
        $this->transactions()->create([
            'currency_id' => $this->currency_id,
            'amount' => $amount,
            'transaction_type' => 'withdrawal',
            'transaction_date' => $date,
            'description' => $description ?? 'Withdrawal',
        ]);

        $this->decrement('current_amount', $amount);
    }
}
