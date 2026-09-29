<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSocial extends Model
{
    use HasFactory;

    protected $fillable = ['person_id', 'network', 'handle', 'url'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
