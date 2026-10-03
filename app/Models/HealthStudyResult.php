<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\ResultFlag;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthStudyResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_id', 'analyte', 'value', 'unit', 'reference_range',
        'flag', 'sort_order', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'flag' => ResultFlag::class,
        ];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(HealthStudy::class, 'study_id');
    }
}
