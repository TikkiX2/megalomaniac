<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroceryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'current_stock',
        'target_stock',
        'unit',
        'price',
        'purchased_at',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'target_stock' => 'decimal:2',
        'price' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priceHistory()
    {
        return $this->hasMany(GroceryPriceHistory::class, 'grocery_item_id', 'id');
    }
}
