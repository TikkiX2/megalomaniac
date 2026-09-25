<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Support\AiProviderResolver;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;
use Throwable;

class ChatService
{
    public function isConfigured(User $user): bool
    {
        return (bool) ($user->ai_enabled && $user->ai_provider_url && $user->ai_provider_key);
    }

    protected function ensureConfigured(User $user): void
    {
        if (! $this->isConfigured($user)) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }
    }

    public function createThread(User $user, string $firstMessage, ?string $model = null): ChatThread
    {
        return ChatThread::create([
            'id' => (string) Str::uuid7(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'title' => Str::limit(trim(strip_tags($firstMessage)), 60, preserveWords: true),
            'agent' => 'megalomaniac',
            'model' => $model,
        ]);
    }

    /**
     * Resolve the user's BYO provider credentials into the runtime config.
     *
     * The config file cannot use closures: laravel/ai v0.11's openai-compatible
     * gateway casts the configured URL to string at request time. OpenCode Go
     * also requires a stable per-conversation session header.
     */
    public function configureUserProvider(User $user, ?string $sessionId = null): void
    {
        $headers = ['User-Agent' => 'megalomaniac-pro/1.0'];

        if ($sessionId !== null && $this->isOpenCodeEndpoint($user->ai_provider_url)) {
            $headers['x-opencode-session'] = $sessionId;
        }

        config([
            'ai.providers.user.url' => $user->ai_provider_url,
            'ai.providers.user.key' => $user->ai_provider_key,
            'ai.providers.user.headers' => $headers,
        ]);
    }

    protected function isOpenCodeEndpoint(?string $url): bool
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) && ($host === 'opencode.ai' || str_ends_with($host, '.opencode.ai'));
    }

    public function streamTurn(User $user, ChatThread $thread, string $message, ?string $model = null): StreamableAgentResponse
    {
        if (! $this->isConfigured($user)) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

        $this->configureUserProvider($user, $thread->id);

        [$provider, $defaultModel] = AiProviderResolver::for($user);

        return (new MegalomaniacAgent($user))
            ->continue($thread->id, as: $user)
            ->stream($message, provider: $provider, model: $model ?: $defaultModel);
    }

    public function regenerate(User $user, ChatThread $thread): ?StreamableAgentResponse
    {
        $this->ensureConfigured($user);

        $content = $this->dropLastExchange($thread);

        return $content === null
            ? null
            : $this->streamTurn($user, $thread, $content, $thread->model);
    }

    public function dropLastExchange(ChatThread $thread): ?string
    {
        $last = $thread->messages()->orderByDesc('id')->first();

        if ($last?->role === 'assistant') {
            $last->delete();
        }

        $userMessage = $thread->messages()->orderByDesc('id')->first();

        if (! $userMessage instanceof ChatMessage || ! $userMessage->isUser()) {
            return null;
        }

        $content = $userMessage->content;
        $userMessage->delete();

        return $content;
    }

    public function editAndResend(User $user, ChatThread $thread, string $messageId, string $content): ?StreamableAgentResponse
    {
        $this->ensureConfigured($user);

        $start = $thread->messages()->whereKey($messageId)->first();

        if (! $start instanceof ChatMessage || ! $start->isUser()) {
            return null;
        }

        $this->truncateFrom($thread, $start);

        return $this->streamTurn($user, $thread, $content, $thread->model);
    }

    public function truncateFrom(ChatThread $thread, ChatMessage $start): void
    {
        $messages = $thread->messages()->orderBy('id')->get();
        $index = $messages->search(fn (ChatMessage $message) => $message->id === $start->id);

        if ($index === false) {
            return;
        }

        $ids = $messages->slice($index)->pluck('id')->all();

        $thread->messages()->whereIn('id', $ids)->delete();
    }

    public function deleteThread(ChatThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $thread->messages()->delete();
            $thread->delete();
        });
    }

    /**
     * @return array<int, string>
     */
    public function availableModels(User $user): array
    {
        $fallback = [$user->ai_model ?: 'gpt-4o-mini'];

        if (! $this->isConfigured($user)) {
            return $fallback;
        }

        return Cache::remember(
            "ai.models.{$user->getKey()}",
            now()->addMinutes(5),
            function () use ($user, $fallback): array {
                try {
                    $response = Http::withToken($user->ai_provider_key)
                        ->acceptJson()
                        ->timeout(5)
                        ->get(rtrim($user->ai_provider_url, '/').'/models');
                } catch (Throwable) {
                    return $fallback;
                }

                if (! $response->successful()) {
                    return $fallback;
                }

                $models = collect($response->json('data', []))
                    ->pluck('id')
                    ->filter(fn ($id) => is_string($id) && $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                return $models === [] ? $fallback : $models;
            }
        );
    }
}
