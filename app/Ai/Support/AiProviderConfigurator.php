<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiProvider;

/**
 * Writes a per-user provider into the runtime `ai` config so laravel/ai can
 * resolve it by name, generalizing the legacy dynamic `'user'` provider into a
 * stable key per registry row.
 *
 * The config file cannot use closures: laravel/ai v0.11 casts the configured
 * URL to string at request time.
 */
class AiProviderConfigurator
{
    /** Stable config key (and `provider:` name) for a registry row. */
    public static function keyFor(AiProvider $provider): string
    {
        return 'pm'.$provider->getKey();
    }

    /**
     * Wire the provider credentials into the config and return its key.
     *
     * Headers always carry the app User-Agent. OpenCode Go additionally
     * requires a stable per-conversation session header, otherwise it rejects
     * the request with 400 MissingSessionID — including for one-shot features
     * with no conversation, which fall back to a stable per-user identifier.
     */
    public static function wire(AiProvider $provider, ?string $sessionId = null): string
    {
        $key = self::keyFor($provider);

        $headers = ['User-Agent' => 'megalomaniac-pro/1.0'];

        if (self::isOpenCodeEndpoint($provider->url)) {
            $headers['x-opencode-session'] = $sessionId ?? 'user-'.$provider->user_id;
        }

        config([
            'ai.providers.'.$key.'.driver' => 'reasoning-compatible',
            'ai.providers.'.$key.'.url' => $provider->url,
            'ai.providers.'.$key.'.key' => $provider->key,
            'ai.providers.'.$key.'.headers' => $headers,
        ]);

        return $key;
    }

    protected static function isOpenCodeEndpoint(?string $url): bool
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) && ($host === 'opencode.ai' || str_ends_with($host, '.opencode.ai'));
    }
}
