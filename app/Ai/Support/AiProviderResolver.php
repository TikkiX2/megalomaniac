<?php

namespace App\Ai\Support;

use App\Models\User;

class AiProviderResolver
{
    /**
     * Resolve the AI provider and model for a user.
     *
     * When the user has configured their own OpenAI-compatible endpoint
     * (Settings → IA), use the "user" provider from config/ai.php.
     * Otherwise fall back to the server default provider.
     *
     * @return array{provider: ?string, model: ?string}
     */
    public static function for(User $user): array
    {
        if (! $user->ai_enabled || ! $user->ai_provider_url || ! $user->ai_provider_key) {
            return [null, null];
        }

        return ['user', $user->ai_model ?: 'gpt-4o-mini'];
    }

    /**
     * Resolve the embeddings provider for a user: their BYO embeddings model
     * when configured, otherwise the server default when a key exists.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function embeddingsFor(User $user): array
    {
        if (
            $user->ai_enabled
            && $user->ai_provider_url
            && $user->ai_provider_key
            && filled($user->ai_embeddings_model)
        ) {
            return ['user', $user->ai_embeddings_model];
        }

        if (filled(config('ai.providers.openai.key'))) {
            return [config('ai.default_for_embeddings'), null];
        }

        return [null, null];
    }
}
