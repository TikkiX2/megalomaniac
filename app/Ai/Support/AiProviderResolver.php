<?php

namespace App\Ai\Support;

use App\Models\User;

class AiProviderResolver
{
    /**
     * Resolve the AI provider and model for a user, wiring the user's BYO
     * credentials into the runtime config before the provider is built.
     *
     * The config file cannot use closures: laravel/ai v0.11's openai-compatible
     * gateway casts the configured URL to string at request time.
     *
     * @return array{provider: ?string, model: ?string}
     */
    public static function for(User $user, ?string $sessionId = null): array
    {
        if (! $user->ai_enabled || ! $user->ai_provider_url || ! $user->ai_provider_key) {
            return [null, null];
        }

        self::configureUserProvider($user, $sessionId);

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
            self::configureUserProvider($user);

            return ['user', $user->ai_embeddings_model];
        }

        if (filled(config('ai.providers.openai.key'))) {
            return [config('ai.default_for_embeddings'), null];
        }

        return [null, null];
    }

    /**
     * Resolve the user's BYO provider credentials into the runtime config.
     *
     * OpenCode Go also requires a stable per-conversation session header.
     */
    public static function configureUserProvider(User $user, ?string $sessionId = null): void
    {
        $headers = ['User-Agent' => 'megalomaniac-pro/1.0'];

        if ($sessionId !== null && self::isOpenCodeEndpoint($user->ai_provider_url)) {
            $headers['x-opencode-session'] = $sessionId;
        }

        config([
            'ai.providers.user.url' => $user->ai_provider_url,
            'ai.providers.user.key' => $user->ai_provider_key,
            'ai.providers.user.headers' => $headers,
        ]);
    }

    protected static function isOpenCodeEndpoint(?string $url): bool
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) && ($host === 'opencode.ai' || str_ends_with($host, '.opencode.ai'));
    }
}
