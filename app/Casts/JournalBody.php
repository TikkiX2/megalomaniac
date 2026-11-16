<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

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
        if (! is_string($value) || ! $this->wasStoredPrivate($model, $attributes)) {
            return $value;
        }

        if (! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (DecryptException) {
            Log::warning('No se pudo descifrar el texto del diario.', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'attribute' => $key,
            ]);

            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $isPrivate = (bool) ($attributes['is_private'] ?? $model->getRawOriginal('is_private'));

        return $isPrivate ? self::PREFIX.Crypt::encryptString($value) : $value;
    }

    /**
     * Whether the current value may be ciphertext. While `is_private` is being
     * toggled the in-memory value could have been written for either state, so
     * both the previous and the new flag count; outside a toggle the row's flag
     * decides, which keeps a public body starting with the prefix untouched.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function wasStoredPrivate(Model $model, array $attributes): bool
    {
        $current = (bool) ($attributes['is_private'] ?? false);

        if ($model->isDirty('is_private')) {
            return $current || (bool) $model->getRawOriginal('is_private');
        }

        return (bool) ($attributes['is_private'] ?? $model->getRawOriginal('is_private'));
    }
}
