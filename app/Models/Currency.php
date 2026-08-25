<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class, 'default_currency_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function convertTo(Currency $target, float $amount, ?Carbon $date = null): ?float
    {
        if ($this->id === $target->id) {
            return $amount;
        }

        $date = $date ?? now();

        $rate = ExchangeRate::where('from_currency_id', $this->id)
            ->where('to_currency_id', $target->id)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        if (! $rate) {
            return null;
        }

        return $amount * $rate->rate;
    }
}
