<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'project_id',
        'quote_number',
        'title',
        'issue_date',
        'valid_until',
        'status',
        'subtotal',
        'tax_percentage',
        'tax_amount',
        'total',
        'currency_id',
        'hourly_rate',
        'notes',
        'terms_and_conditions',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('order');
    }

    public static function generateQuoteNumber()
    {
        $lastQuote = self::latest()->first();
        $nextId = $lastQuote ? $lastQuote->id + 1 : 1;

        return 'QT-'.date('Y').'-'.str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }
}
