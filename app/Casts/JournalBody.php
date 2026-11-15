<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Guarda texto en claro o cifrado por fila, según el flag `is_private` del modelo.
 */
class JournalBody implements CastsAttributes
{
    public const PREFIX = 'enc:v1:';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        $isPrivate = array_key_exists('is_private', $attributes)
            ? (bool) $attributes['is_private']
            : (bool) $model->is_private;

        return $isPrivate ? self::PREFIX.Crypt::encryptString($value) : $value;
    }
}
