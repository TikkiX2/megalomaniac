<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Enums\AiScope;
use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiProviderConfigurator;
use App\Ai\Support\AiProviderErrors;
use App\Ai\Support\AiResolution;
use App\Ai\Support\AiScopeResolver;
use App\Ai\Support\AiStreamFailover;
use App\Ai\Support\ByoProviderMigrator;
use App\Ai\Tools\ToolCatalog;
use App\Ai\Tools\ToolRouter;
use App\Ai\Web\TavilyClient;
use App\Models\AgentDefinition;
use App\Models\AiProvider;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Skill;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
     * Stream failover of the last built turn, or null when no turn was built
     * (or the turn predates the scope cutover). The controller drives it while
     * iterating the stream: `retry()` either hands back a stream rebuilt on the
     * next provider of the chain or declines, and `lastError()` then holds the
     * exhausted-chain diagnosis.
     */
    public ?AiStreamFailover $lastFailover = null;

    /** Registry provider the last built attempt is running on. */
    public ?string $lastProviderName = null;

    /** Model the last built attempt is running with. */
    public ?string $lastProviderModel = null;

    /** True when the last built attempt is running on a backup provider. */
    public bool $lastFallbackUsed = false;

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

    /**
     * Hard ceiling for the images re-embedded on a single turn (base64 grows
     * them by ~33% before the provider request is built).
     */
    public const MAX_TURN_IMAGE_BYTES = 20 * 1024 * 1024;

    public const MAX_SINGLE_IMAGE_BYTES = 8 * 1024 * 1024;

    /**
     * Both dependencies are optional so `new ChatService` keeps working in
     * tests; they are resolved from the container (and therefore shared with
     * the resolver's own singleton state) on first use.
     */
    public function __construct(
        private ?AiScopeResolver $resolver = null,
        private ?AiHealthService $health = null,
        private readonly AiProviderErrors $errors = new AiProviderErrors,
    ) {}

    /** The shared scope resolver, resolved lazily from the container. */
    protected function resolver(): AiScopeResolver
    {
        return $this->resolver ??= app(AiScopeResolver::class);
    }

    /** The shared health service, resolved lazily from the container. */
    protected function health(): AiHealthService
    {
        return $this->health ??= app(AiHealthService::class);
    }

    public function isConfigured(User $user): bool
    {
        return (bool) $user->ai_enabled
            && AiProvider::query()->where('user_id', $user->getKey())->where('enabled', true)->exists();
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

    /**
     * A thread is born in the module its first message was sent from, and stays
     * there: the module decides its prompt layer and provider chain for every
     * later turn. A null (or unknown) module means the general chat.
     */
    public function createThread(
        User $user,
        string $firstMessage,
        ?string $model = null,
        string $agent = 'megalomaniac',
        ?string $module = null,
    ): ChatThread {
        return ChatThread::create([
            'id' => (string) Str::uuid7(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'title' => Str::limit(trim(strip_tags($firstMessage)), 60, preserveWords: true),
            'agent' => $agent,
            'model' => $model,
            'module' => $this->normalizeModuleKey($module),
        ]);
    }

    /**
     * Wire the chat-scope primary provider into the runtime config.
     *
     * No-op when the user has no usable provider: every caller of this method
     * already guarded on {@see self::isConfigured()}, and a silent no-op keeps
     * it usable as a "make the provider available" helper.
     */
    public function configureUserProvider(User $user, ?string $sessionId = null): void
    {
        $provider = $this->resolver()->resolve($user, AiScope::SurfaceChat)->primary();

        if ($provider === null) {
            return;
        }

        AiProviderConfigurator::wire($provider, $sessionId);
    }

    /**
     * @param  array<string, mixed>|null  $toolsPolicy
     * @param  array<int, string>|null  $attachmentIds
     * @param  array<int, string>  $skillKeys  Skills explicitly selected for this turn
     * @param  array<int, int>  $skipProviderIds  Provider ids the failover has
     *                                            already burned through; they are
     *                                            excluded from the resolved chain.
     */
    public function streamTurn(
        User $user,
        ChatThread $thread,
        string $message,
        ?string $model = null,
        ?array $toolsPolicy = null,
        ?array $attachmentIds = null,
        bool $forceWeb = false,
        array $skillKeys = [],
        array $skipProviderIds = [],
    ): StreamableAgentResponse {
        $this->lastWebSources = [];
        $this->lastWebWarning = null;

        if (! $this->isConfigured($user)) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

        $this->pruneMissingStoredImages($thread);

        // Failing here is far better than letting Guzzle exhaust PHP's memory
        // midway through the stream, which would leave the UI stuck waiting.
        $this->guardImagePayload($user, $thread, $attachmentIds);

        $resolution = $this->chatResolution($user, $thread, $skipProviderIds);

        if ($resolution->isEmpty()) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

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
            $agent->withPersonalization($resolution->promptBlock);
        }

        if ($agent instanceof MegalomaniacAgent && $skillKeys !== []) {
            $agent->withSkills($this->resolveSkills($user, $skillKeys));
        }

        if ($agent instanceof MegalomaniacAgent && in_array($this->sourceMode($thread), self::LOCAL_SOURCE_MODES, true)) {
            $agent->withDocumentContext($message);
        }

        if ($forceWeb) {
            if (! $agent instanceof MegalomaniacAgent) {
                $this->lastWebWarning = 'La búsqueda web forzada aún no está disponible con agentes personalizados.';
            } elseif (in_array($this->sourceMode($thread), self::WEB_SOURCE_MODES, true) && filled(trim($message))) {
                $this->forceWebSearch($user, $message, $agent);
            }
        }

        $build = fn (AiProvider $provider): StreamableAgentResponse => $this->buildStreamAttempt(
            $user,
            $thread,
            $agent,
            $message,
            $attachments,
            $provider,
            $model,
        );

        return $this->streamWithFailover($user, $thread, $resolution, $build);
    }

    /**
     * Wire one provider into the runtime config and start its stream.
     *
     * @param  array<int, StoredImage>  $attachments
     */
    protected function buildStreamAttempt(
        User $user,
        ChatThread $thread,
        MegalomaniacAgent|RuntimeAgent $agent,
        string|Decisions $message,
        array $attachments,
        AiProvider $provider,
        ?string $model = null,
    ): StreamableAgentResponse {
        $model = $model ?: $provider->model ?: ByoProviderMigrator::DEFAULT_MODEL;

        $this->lastProviderName = $provider->name;
        $this->lastProviderModel = $model;

        return $agent
            ->continue($thread->id, as: $user)
            ->stream(
                $message,
                attachments: $attachments,
                provider: AiProviderConfigurator::wire($provider, $thread->id),
                model: $model,
            );
    }

    /**
     * Install the failover guard on the already-resolved chain and build its
     * first attempt.
     *
     * @param  callable(AiProvider): StreamableAgentResponse  $build
     */
    protected function streamWithFailover(User $user, ChatThread $thread, AiResolution $resolution, Closure $build): StreamableAgentResponse
    {
        $primary = $resolution->primary();

        if ($primary === null) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

        $this->lastFailover = new AiStreamFailover(
            $primary,
            $resolution,
            $this->health(),
            $this->errors,
            fn (array $tried): StreamableAgentResponse => $this->rebuildStreamAttempt($user, $thread, $tried, $build),
        );
        $this->lastProviderName = $primary->name;
        $this->lastProviderModel = $primary->model;
        $this->lastFallbackUsed = false;

        return $build($primary);
    }

    /**
     * Re-resolve the chain without the burned provider ids and rebuild the turn
     * on the new primary. A null primary would mean the failover handed us a
     * chain it had already exhausted, i.e. an application bug.
     *
     * @param  array<int, int>  $tried
     * @param  callable(AiProvider): StreamableAgentResponse  $build
     */
    protected function rebuildStreamAttempt(User $user, ChatThread $thread, array $tried, Closure $build): StreamableAgentResponse
    {
        $next = $this->chatResolution($user, $thread, $tried)->primary();

        if ($next === null) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

        $this->lastProviderName = $next->name;
        $this->lastProviderModel = $next->model;
        $this->lastFallbackUsed = true;

        return $build($next);
    }

    /**
     * The ordered provider chain for a chat turn in this thread's context,
     * minus the providers the failover already burned through.
     *
     * @param  array<int, int>  $skipProviderIds
     */
    protected function chatResolution(User $user, ChatThread $thread, array $skipProviderIds = []): AiResolution
    {
        $resolution = $this->resolver()->resolve(
            $user,
            AiScope::SurfaceChat,
            $this->moduleKeyFor($thread),
            $thread->id,
        );

        if ($skipProviderIds === []) {
            return $resolution;
        }

        return new AiResolution(
            chain: $resolution->chain
                ->reject(fn (AiProvider $provider): bool => in_array($provider->getKey(), $skipProviderIds, true))
                ->values(),
            promptBlock: $resolution->promptBlock,
        );
    }

    /**
     * The module whose prompt layer and provider scope apply to this thread, or
     * null when the thread has none.
     *
     * Health threads predate the `module` column and carry `category = 'salud'`
     * instead; an unknown value degrades to "no module".
     */
    protected function moduleKeyFor(ChatThread $thread): ?string
    {
        return $this->normalizeModuleKey($thread->module)
            ?? ($thread->category === ChatThread::CATEGORY_HEALTH ? 'health' : null);
    }

    /**
     * The given key when it names a module we know, null otherwise. Anything
     * else degrades to "no module": resolving it would make
     * `AiScope::fromModuleKey()` throw mid-turn.
     */
    protected function normalizeModuleKey(mixed $module): ?string
    {
        return is_string($module) && AiScope::tryFrom('module:'.$module) !== null
            ? $module
            : null;
    }

    /**
     * Run the "search always" pre-search for a turn. Success feeds the agent
     * prompt through InjectWebSearchContext; any failure (missing key, HTTP
     * error) leaves a readable warning for the controller and the turn
     * continues without web context.
     */
    protected function forceWebSearch(User $user, string $message, MegalomaniacAgent $agent): void
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

        $this->lastWebSources = $result['results'] ?? [];
        $agent->withWebSearchContext($this->lastWebSources);
    }

    /**
     * Resolve the explicitly selected skills into instruction blocks for the
     * turn, preserving the picker order.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, name: string, instructions: string}>
     */
    protected function resolveSkills(User $user, array $keys): array
    {
        $skills = Skill::query()
            ->forUser($user)
            ->enabled()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        return collect($keys)
            ->map(fn (string $key): ?array => ($skill = $skills->get($key)) === null ? null : [
                'key' => $skill->key,
                'name' => $skill->name,
                'instructions' => $skill->instructions,
            ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resume a turn paused on tool approvals with the user's decisions.
     */
    public function decide(User $user, ChatThread $thread, Decisions $decisions): StreamableAgentResponse
    {
        $this->lastWebSources = [];
        $this->lastWebWarning = null;

        $this->ensureConfigured($user);

        $resolution = $this->chatResolution($user, $thread);

        if ($resolution->isEmpty()) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

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

        if ($agent instanceof MegalomaniacAgent) {
            $agent->withPersonalization($resolution->promptBlock);
        }

        if (
            $agent instanceof MegalomaniacAgent
            && $lastUserMessage instanceof ChatMessage
            && in_array($this->sourceMode($thread), self::LOCAL_SOURCE_MODES, true)
        ) {
            $agent->withResumeDocumentContext($thread->documentContext($lastUserMessage->content));
        }

        $build = fn (AiProvider $provider): StreamableAgentResponse => $this->buildStreamAttempt(
            $user,
            $thread,
            $agent,
            $decisions,
            [],
            $provider,
            $thread->model,
        );

        return $this->streamWithFailover($user, $thread, $resolution, $build);
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

        $groups = ToolRouter::route($message);

        if (! in_array('memory', $groups, true)) {
            $groups[] = 'memory';
        }

        return ['mode' => 'auto', 'groups' => $groups];
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

            // Images survive in the sources library: unlink them from the
            // thread instead of destroying files the user may want to reuse.
            ChatAttachment::query()
                ->where('kind', 'image')
                ->where(function ($query) use ($messageIds, $thread): void {
                    $query->whereIn('message_id', $messageIds)
                        ->orWhere('thread_id', $thread->id);
                })
                ->update(['message_id' => null, 'thread_id' => null]);

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
     * Reject a turn whose re-embedded images would exceed the safe payload:
     * the newest history message (the only one the agent keeps) plus the
     * attachments sent with this turn.
     *
     * @param  array<int, string>|null  $attachmentIds
     */
    protected function guardImagePayload(User $user, ChatThread $thread, ?array $attachmentIds): void
    {
        $currentAttachments = ChatAttachment::query()
            ->forUser($user)
            ->whereIn('id', $attachmentIds ?? [])
            ->images()
            ->ready()
            ->get(['id', 'size']);

        foreach ($currentAttachments as $attachment) {
            if ((int) $attachment->size > self::MAX_SINGLE_IMAGE_BYTES) {
                throw ValidationException::withMessages([
                    'attachment_ids' => 'Cada imagen puede pesar como máximo 8 MB.',
                ]);
            }
        }

        $historyBytes = 0;
        $lastUserMessage = $thread->messages()->where('role', 'user')->orderByDesc('id')->first();

        foreach ((array) ($lastUserMessage?->attachments ?? []) as $attachment) {
            if (! is_array($attachment) || ($attachment['type'] ?? null) !== 'stored-image') {
                continue;
            }

            $path = $attachment['path'] ?? null;
            $disk = $attachment['disk'] ?? null;

            if (is_string($path) && is_string($disk) && Storage::disk($disk)->exists($path)) {
                $historyBytes += (int) Storage::disk($disk)->size($path);
            }
        }

        $totalBytes = $historyBytes + (int) $currentAttachments->sum('size');

        if ($totalBytes > self::MAX_TURN_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'attachment_ids' => sprintf(
                    'Las imágenes del turno pesan demasiado (%.1f MB; el máximo es 20 MB). Enviá menos imágenes o más chicas.',
                    $totalBytes / 1048576,
                ),
            ]);
        }
    }

    /**
     * Prompt history can outlive the files it references (an image removed
     * after being sent keeps its descriptor). Re-sending that history builds
     * empty `data:image/...;base64,` parts, which the provider rejects with a
     * 400, so dangling stored images are pruned before the next turn.
     */
    protected function pruneMissingStoredImages(ChatThread $thread): void
    {
        $messages = $thread->messages()->whereNotNull('attachments')->get();

        foreach ($messages as $message) {
            $attachments = $message->attachments;

            if (! is_array($attachments) || $attachments === []) {
                continue;
            }

            $kept = array_values(array_filter($attachments, function (mixed $attachment): bool {
                if (! is_array($attachment) || ($attachment['type'] ?? null) !== 'stored-image') {
                    return true;
                }

                $path = $attachment['path'] ?? null;
                $disk = $attachment['disk'] ?? null;

                if (! is_string($path) || ! is_string($disk)) {
                    return false;
                }

                $storage = Storage::disk($disk);

                // An empty file would be re-embedded as an empty base64 part
                // and the provider rejects the whole request with a 400.
                return $storage->exists($path) && (int) $storage->size($path) > 0;
            }));

            if (count($kept) !== count($attachments)) {
                $message->update(['attachments' => $kept]);
            }
        }
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
