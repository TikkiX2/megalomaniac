<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroceryPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'grocery_price_history';

    protected $fillable = [
        'grocery_item_id',
        'price',
        'quantity',
        'purchased_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function groceryItem(): BelongsTo
    {
        return $this->belongsTo(GroceryItem::class, 'grocery_item_id', 'id');
    }
}
