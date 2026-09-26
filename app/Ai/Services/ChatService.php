<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Support\AiProviderResolver;
use App\Ai\Tools\ToolCatalog;
use App\Ai\Tools\ToolRouter;
use App\Models\AgentDefinition;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;
use Throwable;

class ChatService
{
    /**
     * Policy resolved for the last streamed turn (for SSE/UI).
     *
     * @var array{mode: string, groups: string[]}
     */
    public array $lastToolPolicy = [];

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

    public function createThread(User $user, string $firstMessage, ?string $model = null, string $agent = 'megalomaniac'): ChatThread
    {
        return ChatThread::create([
            'id' => (string) Str::uuid7(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'title' => Str::limit(trim(strip_tags($firstMessage)), 60, preserveWords: true),
            'agent' => $agent,
            'model' => $model,
        ]);
    }

    /**
     * Resolve the user's BYO provider credentials into the runtime config.
     */
    public function configureUserProvider(User $user, ?string $sessionId = null): void
    {
        AiProviderResolver::configureUserProvider($user, $sessionId);
    }

    /**
     * @param  array<string, mixed>|null  $toolsPolicy
     * @param  array<int, string>|null  $attachmentIds
     */
    public function streamTurn(
        User $user,
        ChatThread $thread,
        string $message,
        ?string $model = null,
        ?array $toolsPolicy = null,
        ?array $attachmentIds = null,
    ): StreamableAgentResponse {
        if (! $this->isConfigured($user)) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

        [$provider, $defaultModel] = AiProviderResolver::for($user, $thread->id);

        $policy = $this->prepareToolPolicy($thread, $message, $toolsPolicy);
        $this->lastToolPolicy = $policy;

        $attachments = ChatAttachment::query()
            ->forUser($user)
            ->whereIn('id', $attachmentIds ?? [])
            ->images()
            ->ready()
            ->get()
            ->map(fn (ChatAttachment $attachment) => new StoredImage($attachment->path, $attachment->disk))
            ->all();

        $agent = $this->agentFor($user, $thread, $policy['groups']);

        if ($agent instanceof MegalomaniacAgent) {
            $agent->withDocumentContext($message);
        }

        return $agent
            ->continue($thread->id, as: $user)
            ->stream($message, attachments: $attachments, provider: $provider, model: $model ?: $defaultModel);
    }

    /**
     * Resume a turn paused on tool approvals with the user's decisions.
     */
    public function decide(User $user, ChatThread $thread, Decisions $decisions): StreamableAgentResponse
    {
        $this->ensureConfigured($user);
        $this->configureUserProvider($user, $thread->id);

        [$provider, $defaultModel] = AiProviderResolver::for($user, $thread->id);

        // ['*'] on purpose: a resume must be able to resolve every tool the
        // paused turn advertised, and AskUserTool is always appended by the agent.
        return (new MegalomaniacAgent($user, ['*'], $thread))
            ->continue($thread->id, as: $user)
            ->stream($decisions, provider: $provider, model: $thread->model ?: $defaultModel);
    }

    /**
     * Resolve the tool groups for a turn. A manual override is persisted on
     * the thread; without one, the thread policy (manual) or the router wins.
     *
     * @param  array<string, mixed>|null  $override
     * @return array{mode: string, groups: string[]}
     */
    public function prepareToolPolicy(ChatThread $thread, string $message, ?array $override = null): array
    {
        if (is_array($override)) {
            $policy = $this->normalizeToolPolicy($override, $message);
            $thread->forceFill(['tools_policy' => $policy])->save();

            return $policy;
        }

        return $this->normalizeToolPolicy($thread->tools_policy, $message);
    }

    /**
     * @param  array<string, mixed>|null  $policy
     * @return array{mode: string, groups: string[]}
     */
    protected function normalizeToolPolicy(?array $policy, string $message): array
    {
        if (is_array($policy) && ($policy['mode'] ?? null) === 'manual') {
            $groups = array_values(array_filter(
                (array) ($policy['groups'] ?? []),
                fn (mixed $group): bool => is_string($group) && ToolCatalog::isValidGroup($group),
            ));

            if ($groups !== []) {
                return ['mode' => 'manual', 'groups' => $groups];
            }
        }

        return ['mode' => 'auto', 'groups' => ToolRouter::route($message)];
    }

    /**
     * Resolve the thread's agent: a durable AgentDefinition when the thread
     * references one, otherwise the default Megalomaniac agent.
     */
    public function agentFor(User $user, ChatThread $thread, array $toolGroups = ['*']): MegalomaniacAgent|RuntimeAgent
    {
        if (filled($thread->agent) && $thread->agent !== 'megalomaniac') {
            $definition = AgentDefinition::query()
                ->forUser($user)
                ->where('key', $thread->agent)
                ->first();

            if ($definition) {
                return new RuntimeAgent($definition, []);
            }
        }

        return new MegalomaniacAgent($user, $toolGroups, $thread);
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
