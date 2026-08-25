<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'income_id',
        'amount',
        'payment_date',
        'expected_date',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'expected_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    protected static function booted()
    {
        static::saved(function ($payment) {
            if ($payment->project) {
                $payment->project->updatePaidAmount();
            }
        });

        static::deleted(function ($payment) {
            if ($payment->project) {
                $payment->project->updatePaidAmount();
            }
        });
    }
}
