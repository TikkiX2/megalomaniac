<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Support\AiProviderResolver;
use App\Ai\Tools\ToolCatalog;
use App\Ai\Tools\ToolRouter;
use App\Ai\Web\TavilyClient;
use App\Models\AgentDefinition;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Results of the forced "search always" pre-search for the last turn.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lastWebSources = [];

    /**
     * Warning produced by the forced "search always" pre-search, if any.
     */
    public ?string $lastWebWarning = null;

    /**
     * Source modes that include the web tool group.
     *
     * @var array<int, string>
     */
    public const WEB_SOURCE_MODES = ['web', 'both'];

    /**
     * Source modes that inject thread document context.
     *
     * @var array<int, string>
     */
    public const LOCAL_SOURCE_MODES = ['local', 'both'];

    public function isConfigured(User $user): bool
    {
        return (bool) ($user->ai_enabled && $user->ai_provider_url && $user->ai_provider_key);
    }

    /**
     * Resolve the thread's source mode, defaulting to "both" when unset or
     * invalid. The mode governs only the web tool group and the document
     * context middleware; data groups are untouched.
     */
    public function sourceMode(?ChatThread $thread): string
    {
        $mode = $thread?->mode;

        return in_array($mode, ['web', 'local', 'both', 'off'], true) ? $mode : 'both';
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
        bool $forceWeb = false,
    ): StreamableAgentResponse {
        $this->lastWebSources = [];
        $this->lastWebWarning = null;

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

        if ($agent instanceof MegalomaniacAgent && in_array($this->sourceMode($thread), self::LOCAL_SOURCE_MODES, true)) {
            $agent->withDocumentContext($message);
        }

        if ($forceWeb && in_array($this->sourceMode($thread), self::WEB_SOURCE_MODES, true) && filled(trim($message))) {
            $this->forceWebSearch($user, $message, $agent);
        }

        return $agent
            ->continue($thread->id, as: $user)
            ->stream($message, attachments: $attachments, provider: $provider, model: $model ?: $defaultModel);
    }

    /**
     * Run the "search always" pre-search for a turn. Success feeds the agent
     * prompt through InjectWebSearchContext; any failure (missing key, HTTP
     * error) leaves a readable warning for the controller and the turn
     * continues without web context.
     */
    protected function forceWebSearch(User $user, string $message, MegalomaniacAgent|RuntimeAgent $agent): void
    {
        $client = TavilyClient::for($user);

        if ($client === null) {
            $this->lastWebWarning = 'Configura tu API key de Tavily en Settings → IA para buscar en la web.';

            return;
        }

        $result = $client->search($message, ['max_results' => 6]);

        if (isset($result['error'])) {
            $this->lastWebWarning = $result['error'];

            return;
        }

        $this->lastWebSources = $result['results'];

        if ($agent instanceof MegalomaniacAgent) {
            $agent->withWebSearchContext($result['results']);
        }
    }

    /**
     * Resume a turn paused on tool approvals with the user's decisions.
     */
    public function decide(User $user, ChatThread $thread, Decisions $decisions): StreamableAgentResponse
    {
        $this->lastWebSources = [];
        $this->lastWebWarning = null;

        $this->ensureConfigured($user);
        $this->configureUserProvider($user, $thread->id);

        [$provider, $defaultModel] = AiProviderResolver::for($user, $thread->id);

        $lastUserMessage = $thread->messages()
            ->where('role', 'user')
            ->orderByDesc('id')
            ->first();

        // Resolve the agent exactly like streamTurn so a custom-agent thread
        // resumes with its own persona and tools. The resumed turn must
        // advertise the same tools the paused turn did: manual overrides are
        // persisted on the thread, and auto mode is reproduced by re-routing
        // the user message that started the paused turn. Resuming with ['*']
        // would expose tools the user had explicitly disabled.
        $policy = $this->prepareToolPolicy($thread, $lastUserMessage?->content ?? '');
        $this->lastToolPolicy = $policy;

        $agent = $this->agentFor($user, $thread, $policy['groups']);

        if (
            $agent instanceof MegalomaniacAgent
            && $lastUserMessage instanceof ChatMessage
            && in_array($this->sourceMode($thread), self::LOCAL_SOURCE_MODES, true)
        ) {
            $agent->withResumeDocumentContext($thread->documentContext($lastUserMessage->content));
        }

        return $agent
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
        $policy = $this->normalizeToolPolicy(
            is_array($override) ? $override : $thread->tools_policy,
            $message,
        );

        if (is_array($override)) {
            // Persist the raw manual choice: the thread source mode filters the
            // effective turn, so switching modes back restores the web group.
            $thread->forceFill(['tools_policy' => $policy])->save();
        }

        return $this->applySourceMode($thread, $policy);
    }

    /**
     * Drop the web group when the thread source mode excludes it. Both
     * streamTurn and decide() resolve their policy through this path.
     *
     * @param  array{mode: string, groups: string[]}  $policy
     * @return array{mode: string, groups: string[]}
     */
    protected function applySourceMode(ChatThread $thread, array $policy): array
    {
        if (in_array($this->sourceMode($thread), self::WEB_SOURCE_MODES, true)) {
            return $policy;
        }

        $policy['groups'] = array_values(array_filter(
            $policy['groups'],
            fn (string $group): bool => $group !== 'web',
        ));

        return $policy;
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
        $deletedIds = [];

        $last = $thread->messages()->orderByDesc('id')->first();

        if ($last?->role === 'assistant') {
            $deletedIds[] = $last->id;
            $last->delete();
        }

        $userMessage = $thread->messages()->orderByDesc('id')->first();

        if (! $userMessage instanceof ChatMessage || ! $userMessage->isUser()) {
            $this->detachAttachments($deletedIds);

            return null;
        }

        $content = $userMessage->content;
        $deletedIds[] = $userMessage->id;
        $userMessage->delete();

        $this->detachAttachments($deletedIds);

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

        $this->detachAttachments($ids);

        $thread->messages()->whereIn('id', $ids)->delete();
    }

    public function deleteThread(ChatThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $messageIds = $thread->messages()->pluck('id')->all();

            ChatAttachment::query()
                ->where('kind', 'image')
                ->whereIn('message_id', $messageIds)
                ->get()
                ->each(function (ChatAttachment $attachment): void {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                    $attachment->delete();
                });

            $thread->sources()->detach();
            $thread->messages()->delete();
            $thread->delete();
        });
    }

    /**
     * Detach attachments from deleted messages without destroying the files:
     * documents are thread-level context and images stay reusable until the
     * user deletes them explicitly.
     *
     * @param  array<int, string>  $messageIds
     */
    protected function detachAttachments(array $messageIds): void
    {
        if ($messageIds === []) {
            return;
        }

        ChatAttachment::query()
            ->whereIn('message_id', $messageIds)
            ->update(['message_id' => null]);
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
