<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    protected $fillable = [
        'user_id',
        'purchase_id',
        'credit_card_id',
        'currency_id',
        'original_amount',
        'remaining_amount',
        'interest_amount',
        'tax_amount',
        'total_amount',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('due_date', '<', now());
    }

    // Methods
    public function addPayment(float $amount, Carbon $date, ?string $notes = null): DebtPayment
    {
        $payment = $this->payments()->create([
            'currency_id' => $this->currency_id,
            'amount' => $amount,
            'payment_date' => $date,
            'notes' => $notes,
        ]);

        $this->remaining_amount -= $amount;

        if ($this->remaining_amount <= 0) {
            $this->remaining_amount = 0;
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }

        $this->save();

        return $payment;
    }

    public function markAsPaid(): void
    {
        $this->update([
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);
    }

    public function getRemainingAmount(): float
    {
        return (float) $this->remaining_amount;
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' &&
               $this->due_date &&
               $this->due_date->isPast();
    }
}
