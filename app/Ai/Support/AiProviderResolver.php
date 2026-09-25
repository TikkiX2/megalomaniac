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
}
