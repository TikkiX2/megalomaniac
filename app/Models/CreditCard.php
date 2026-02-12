<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCard extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'last_four_digits',
        'owner_id',
        'is_mine',
        'interest_rate',
        'tax_percentage',
        'apply_interest',
        'apply_tax',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_mine' => 'boolean',
            'interest_rate' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'apply_interest' => 'boolean',
            'apply_tax' => 'boolean',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    // Scopes
    public function scopeMine($query)
    {
        return $query->where('is_mine', true);
    }

    public function scopeBorrowed($query)
    {
        return $query->where('is_mine', false);
    }

    // Methods
    public function calculateInterest(float $amount): float
    {
        if (! $this->apply_interest) {
            return 0;
        }

        return $amount * ($this->interest_rate / 100);
    }

    public function calculateTax(float $amount): float
    {
        if (! $this->apply_tax) {
            return 0;
        }

        return $amount * ($this->tax_percentage / 100);
    }

    public function calculateTotalAmount(float $originalAmount): float
    {
        $interest = $this->calculateInterest($originalAmount);
        $tax = $this->calculateTax($originalAmount);

        return $originalAmount + $interest + $tax;
    }
}
