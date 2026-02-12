<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends Model
{
    protected $fillable = [
        'user_id',
        'currency_id',
        'category_id',
        'amount',
        'purchase_date',
        'description',
        'notes',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purchase_date' => 'date',
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
        return $this->belongsTo(PurchaseCategory::class, 'category_id');
    }

    public function debt(): HasOne
    {
        return $this->hasOne(Debt::class);
    }

    // Scopes
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('purchase_date', [$start, $end]);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Methods
    public function hasDebt(): bool
    {
        return $this->debt()->exists();
    }

    public function convertTo(Currency $target): ?float
    {
        return $this->currency->convertTo($target, $this->amount, $this->purchase_date);
    }
}
