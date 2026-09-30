<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Ai\Enums\AiScope;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;

/**
 * Convierte las credenciales BYO legacy del usuario (`users.ai_*`) en el
 * registro de proveedores: un `AiProvider` llamado "Principal" más la fila
 * `global` de `ai_scopes` apuntando a ese provider.
 *
 * Idempotente: si el usuario no tiene credenciales BYO, o ya tiene un
 * provider "Principal", no toca nada. Las columnas legacy se conservan.
 */
class ByoProviderMigrator
{
    public const DEFAULT_MODEL = 'gpt-4o-mini';

    public const PROVIDER_NAME = 'Principal';

    public static function migrate(User $user): void
    {
        if (blank($user->ai_provider_url) || blank($user->ai_provider_key)) {
            return;
        }

        $alreadyMigrated = AiProvider::query()
            ->where('user_id', $user->getKey())
            ->where('name', self::PROVIDER_NAME)
            ->exists();

        if ($alreadyMigrated) {
            return;
        }

        $provider = AiProvider::query()->create([
            'user_id' => $user->getKey(),
            'name' => self::PROVIDER_NAME,
            'url' => $user->ai_provider_url,
            'key' => $user->ai_provider_key,
            'model' => $user->ai_model ?: self::DEFAULT_MODEL,
            'embeddings_model' => $user->ai_embeddings_model,
        ]);

        UserAiScope::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'scope' => AiScope::Global->value,
            ],
            ['provider_chain' => [$provider->getKey()]],
        );
    }
}
