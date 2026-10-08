<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Enums\AiScope;
use App\Ai\Services\ChatService;
use App\Ai\Skills\SkillCatalog;
use App\Ai\Support\AiAllProvidersFailedException;
use App\Ai\Support\AiStreamFailover;
use App\Ai\Support\WebCitations;
use App\Ai\Tools\ToolCatalog;
use App\Http\Controllers\Ai\Concerns\ProvidesAiState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ApproveChatTurnRequest;
use App\Http\Requests\Ai\EditChatMessageRequest;
use App\Http\Requests\Ai\SendChatMessageRequest;
use App\Http\Requests\Ai\UpdateChatThreadRequest;
use App\Http\Resources\ChatAttachmentResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatThreadResource;
use App\Models\AgentDefinition;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Laravel\Ai\Exceptions\ApprovalNotResumableException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    use ProvidesAiState;

    private static ?string $lastProviderRequest = null;

    public function __construct(
        protected ChatService $service,
        protected SkillCatalog $skillCatalog,
    ) {
        static $hooked = false;

        if (! $hooked) {
            $hooked = true;

            Http::globalRequestMiddleware(function ($request): mixed {
                self::$lastProviderRequest = (string) $request->getBody();

                return $request;
            });
        }
    }

    public function index(Request $request): Response
    {
        return Inertia::render('ai/chat', $this->chatPageProps($request, null));
    }

    /**
     * The per-module assistant (`/ai/{module}`): the same chat surface with its
     * rail narrowed to the module and the new thread tagged with it. The
     * module also drives the thread's scope, so its prompt layer and provider
     * chain apply from the first turn on.
     */
    public function module(Request $request, string $module): Response
    {
        return Inertia::render('ai/module', $this->chatPageProps($request, $module));
    }

    /**
     * Props shared by the general chat home and the module wrappers, so both
     * surfaces stay in sync. `module` is null on the general chat.
     *
     * @return array<string, mixed>
     */
    protected function chatPageProps(Request $request, ?string $module): array
    {
        $user = $request->user();

        return [
            'threads' => ChatThreadResource::collection($this->threadsFor($user, $module))->resolve($request),
            'models' => $this->service->availableModels($user),
            'agents' => $this->agentsFor($user),
            'toolGroups' => $this->toolGroups(),
            'skills' => $this->skillCatalog->summariesFor($user),
            'ai' => $this->aiState($user),
            'module' => $module,
            // The empty state offers three module-specific prompts; on the
            // general chat (null module) that is an empty list.
            'suggestions' => $module === null ? [] : AiScope::moduleSuggestions($module),
        ];
    }

    public function show(Request $request, ChatThread $thread): Response
    {
        $this->authorize('view', $thread);

        $user = $request->user();

        $thread->loadCount('memories');

        return Inertia::render('ai/thread', [
            'thread' => (new ChatThreadResource($thread))->resolve($request),
            'messages' => ChatMessageResource::collection(
                $thread->messages()->with('attachments')->orderBy('id')->get()
            )->resolve($request),
            'sources' => ChatAttachmentResource::collection(
                $thread->sources()
                    ->where('kind', 'document')
                    ->orderByDesc('chat_thread_sources.created_at')
                    ->limit(20)
                    ->get()
            )->resolve($request),
            'library' => ChatAttachmentResource::collection(
                ChatAttachment::query()
                    ->forUser($user)
                    ->withCount('threads')
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get()
            )->resolve($request),
            'threads' => ChatThreadResource::collection($this->threadsFor($user))->resolve($request),
            'models' => $this->service->availableModels($user),
            'agents' => $this->agentsFor($user),
            'toolGroups' => $this->toolGroups(),
            'skills' => $this->skillCatalog->summariesFor($user),
            'ai' => $this->aiState($user),
        ]);
    }

    public function update(UpdateChatThreadRequest $request, ChatThread $thread): RedirectResponse
    {
        $this->authorize('update', $thread);

        $data = $request->validated();

        if (array_key_exists('title', $data)) {
            $thread->title = $data['title'];
        }

        if (array_key_exists('pinned', $data)) {
            $thread->pinned_at = $data['pinned'] ? now() : null;
        }

        if (array_key_exists('mode', $data)) {
            $thread->mode = $data['mode'];
        }

        if (array_key_exists('category', $data)) {
            $thread->category = $data['category'];

            if ($data['category'] === ChatThread::CATEGORY_HEALTH) {
                // Al entrar en salud: respaldar la policy anterior y fijar el
                // grupo health (paridad con HealthChatController::store).
                $thread->tools_policy_backup = $thread->tools_policy ?? null;
                $thread->tools_policy = ['mode' => 'manual', 'groups' => ['health']];
            } else {
                // Al salir de salud: restaurar la policy respaldada; si no
                // había backup (chat creado directamente como salud), volver
                // al default del agente (null).
                $thread->tools_policy = $thread->tools_policy_backup ?? null;
                $thread->tools_policy_backup = null;
            }
        }

        if (array_key_exists('tools_policy', $data) && ! array_key_exists('category', $data)) {
            $thread->tools_policy = $data['tools_policy'];
        }

        $thread->save();

        return back();
    }

    public function destroy(Request $request, ChatThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $this->service->deleteThread($thread);

        return to_route('ai.chat.index');
    }

    public function models(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->boolean('refresh')) {
            Cache::forget("ai.models.{$user->getKey()}");
        }

        return response()->json([
            'models' => $this->service->availableModels($user),
        ]);
    }

    public function send(SendChatMessageRequest $request): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $this->service->isConfigured($user),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        $threadId = $request->validated('thread_id');
        $model = $request->validated('model');
        $attachmentIds = $request->validated('attachment_ids');

        if ($threadId !== null) {
            $thread = ChatThread::query()->forUser($user)->find($threadId);

            abort_if($thread === null, 404);

            $this->authorize('update', $thread);
        } else {
            // The module only tags a new thread; an existing one keeps the
            // module it was born in (its scope follows the thread).
            $thread = $this->service->createThread(
                $user,
                $request->validated('message'),
                $model,
                (string) ($request->validated('agent') ?? 'megalomaniac'),
                $request->validated('module'),
            );

            // Documents uploaded from the chat home become sources of the
            // freshly created thread (pivot) so their context is injected.
            // Images keep the legacy thread column and are linked later to
            // the stored message.
            $sentAttachments = ChatAttachment::query()
                ->whereIn('id', $attachmentIds ?? [])
                ->where('user_id', $user->getKey())
                ->get();

            $thread->sources()->syncWithoutDetaching(
                $sentAttachments->where('kind', 'document')->pluck('id')->all()
            );

            $imageIds = $sentAttachments->where('kind', 'image')->whereNull('thread_id')->pluck('id')->all();

            if ($imageIds !== []) {
                ChatAttachment::query()
                    ->whereIn('id', $imageIds)
                    ->update(['thread_id' => $thread->id]);
            }
        }

        if ($model !== null) {
            $thread->update(['model' => $model]);
        }

        $stream = $this->service->streamTurn(
            $user,
            $thread,
            $request->validated('message'),
            $model ?? $thread->model,
            $request->validated('tools_policy'),
            $attachmentIds,
            (bool) $request->validated('force_web'),
            $request->validated('skill_keys') ?? [],
        );

        return $this->streamResponse(
            $stream,
            $thread,
            $this->service->lastToolPolicy,
            $attachmentIds,
            $this->service->lastWebWarning,
        );
    }

    public function regenerate(Request $request, ChatThread $thread): StreamedResponse
    {
        $this->authorize('update', $thread);

        abort_unless(
            $this->service->isConfigured($request->user()),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        $stream = $this->service->regenerate($request->user(), $thread);

        abort_if($stream === null, 422, 'No hay ningún intercambio que regenerar.');

        return $this->streamResponse($stream, $thread, $this->service->lastToolPolicy);
    }

    public function edit(EditChatMessageRequest $request, ChatThread $thread): StreamedResponse
    {
        $this->authorize('update', $thread);

        abort_unless(
            $this->service->isConfigured($request->user()),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        $stream = $this->service->editAndResend(
            $request->user(),
            $thread,
            $request->validated('message_id'),
            $request->validated('content'),
        );

        abort_if($stream === null, 422, 'El mensaje indicado no se puede editar.');

        return $this->streamResponse($stream, $thread, $this->service->lastToolPolicy);
    }

    public function approve(ApproveChatTurnRequest $request, ChatThread $thread): StreamedResponse
    {
        $this->authorize('update', $thread);

        abort_unless(
            $this->service->isConfigured($request->user()),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        // Snapshot the paused row before the resume so a failed continuation
        // can put the decision back (see compensateFailedResume).
        $paused = $this->pausedApprovalCandidate($thread);

        $resumeSnapshot = $paused === null ? null : [
            'id' => $paused->getKey(),
            'approval_state' => $paused->approval_state,
            'tool_results' => $paused->tool_results,
        ];

        $decisions = Decisions::from(collect(
            $paused === null
                ? $request->validated('decisions')
                : $this->coalescePendingDecisions($paused, $request->validated('decisions'))
        )->map(function (array|bool $decision): Decision|bool {
            if (is_bool($decision)) {
                return $decision;
            }

            return match ($decision['action']) {
                'approve' => Decision::approve(),
                'edit' => Decision::edit($decision['arguments'] ?? []),
                default => Decision::reject($decision['result'] ?? null),
            };
        })
            ->all());

        try {
            $stream = $this->service->decide($request->user(), $thread, $decisions);
        } catch (ApprovalMismatchException|ApprovalNotResumableException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->streamResponse($stream, $thread, resumeSnapshot: $resumeSnapshot);
    }

    /**
     * The newest approval-paused assistant row of the thread, if any.
     */
    protected function pausedApprovalCandidate(ChatThread $thread): ?ChatMessage
    {
        return $thread->messages()
            ->where('role', 'assistant')
            ->whereNotNull('approval_state')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Complete a partial decisions payload: every pending call without a
     * decision is settled as a rejected result with a placeholder, so
     * answering one of several questions never 422s the whole turn. Unknown
     * ids are left untouched and are still rejected by the SDK.
     *
     * @param  array<string, mixed>  $decisions
     * @return array<string, mixed>
     */
    protected function coalescePendingDecisions(ChatMessage $paused, array $decisions): array
    {
        if (array_key_exists('*', $decisions)) {
            return $decisions;
        }

        $toolByCallId = collect($paused->tool_calls ?? [])
            ->filter(fn (mixed $call): bool => is_array($call))
            ->mapWithKeys(fn (array $call): array => [
                $call['id'] ?? '' => (string) (data_get($call, 'name') ?? data_get($call, 'function.name') ?? ''),
            ])
            ->filter(fn (string $tool): bool => $tool !== '')
            ->all();

        foreach (array_keys((array) ($paused->approval_state['pending'] ?? [])) as $callId) {
            if (array_key_exists($callId, $decisions)) {
                continue;
            }

            $isQuestion = ($toolByCallId[$callId] ?? null) === 'AskUserTool';

            $decisions[$callId] = [
                'action' => 'reject',
                'result' => $isQuestion
                    ? 'El usuario no respondió a esta pregunta (la saltó y el turno continúa con las demás respuestas).'
                    : 'El usuario no respondió esta consulta (el turno continúa sin esta aprobación).',
            ];
        }

        return $decisions;
    }

    /**
     * The forced-web warning travels as an explicit parameter (instead of
     * reading $this->service inside the stream closure) so streamResponse
     * needs no knowledge of ChatService mutable state; only send() can
     * produce one.
     *
     * @param  array{mode?: string, groups?: string[]}  $toolPolicy
     * @param  array<int, string>|null  $attachmentIds
     */
    protected function streamResponse(
        StreamableAgentResponse $stream,
        ChatThread $thread,
        array $toolPolicy = [],
        ?array $attachmentIds = null,
        ?string $webWarning = null,
        ?array $resumeSnapshot = null,
    ): StreamedResponse {
        // The forced pre-search sources are snapshotted before the closure runs
        // (like $webWarning) so it never reads ChatService mutable state.
        $preSearchSources = $this->service->lastWebSources;

        // The failover guard is snapshotted too: the controller drives it while
        // iterating, and it owns the per-attempt decisions from there on.
        $failover = $this->service->lastFailover;
        $providerName = $this->service->lastProviderName;
        $providerModel = $this->service->lastProviderModel;
        $fallbackUsed = $this->service->lastFallbackUsed;

        return response()->stream(function () use ($stream, $thread, $toolPolicy, $attachmentIds, $webWarning, $preSearchSources, $failover, $providerName, $providerModel, $fallbackUsed, $resumeSnapshot): void {
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }

            // A fatal (for example an out-of-memory during the provider read)
            // is not catchable, so the client would wait forever for a stream
            // that will never finish. Best effort: close the SSE turn with an
            // explicit error when the process dies mid-stream.
            $completed = false;

            register_shutdown_function(function () use (&$completed): void {
                if ($completed || ! headers_sent()) {
                    return;
                }

                $error = error_get_last();

                if ($error === null || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    return;
                }

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'La generación se interrumpió por un error del servidor. Inténtalo de nuevo.',
                    'recoverable' => false,
                ])."\n\n";
                echo "data: [DONE]\n\n";
                flush();
            });

            echo 'data: '.json_encode(['type' => 'thread', 'threadId' => $thread->id])."\n\n";
            flush();

            if ($webWarning !== null) {
                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => $webWarning,
                    'recoverable' => true,
                ])."\n\n";
                flush();
            }

            if ($toolPolicy !== []) {
                echo 'data: '.json_encode([
                    'type' => 'tools',
                    'mode' => $toolPolicy['mode'] ?? 'auto',
                    'groups' => $toolPolicy['groups'] ?? [],
                ])."\n\n";
                flush();
            }

            $reasoning = '';
            $reasoningStartedAt = null;
            $assistantIdBefore = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->value('id');
            $userMessageIdBefore = $thread->messages()->where('role', 'user')->orderByDesc('id')->value('id');

            /** @var array<int, array{url: string, title: ?string, snippet: ?string}> $webCitations */
            $webCitations = [];
            $seenCitationUrls = [];

            $failed = false;

            // The provider chain is walked here because laravel/ai streams lazily:
            // a failure only surfaces while iterating. `retry()` hands back a
            // stream rebuilt on the next provider while nothing has reached the
            // browser yet, and returns null as soon as it declines, so the copy
            // below still comes from the original failure.
            while (true) {
                try {
                    foreach ($stream as $event) {
                        $eventArray = $event->toArray();
                        $eventType = $eventArray['type'] ?? null;

                        if ($eventType === 'reasoning_delta') {
                            $reasoningStartedAt ??= microtime(true);
                            $reasoning .= (string) ($eventArray['delta'] ?? '');
                        }

                        echo 'data: '.((string) $event)."\n\n";
                        flush();

                        foreach (WebCitations::fromToolResult($eventArray) as $citation) {
                            if (isset($seenCitationUrls[$citation['url']])) {
                                continue;
                            }

                            $seenCitationUrls[$citation['url']] = true;
                            $webCitations[] = $citation;

                            echo 'data: '.json_encode([
                                'type' => 'citation',
                                'citation' => ['title' => $citation['title'], 'url' => $citation['url']],
                            ])."\n\n";
                            flush();
                        }
                    }

                    break;
                } catch (Throwable $exception) {
                    $retry = $failover?->retry($exception, $stream);

                    if ($retry !== null) {
                        $stream = $retry;

                        continue;
                    }

                    $failed = true;

                    report($failover?->lastError() ?? $exception);

                    $this->logProviderFailure($failover?->lastError() ?? $exception, $thread->id);

                    echo 'data: '.json_encode([
                        'type' => 'error',
                        'message' => $this->errorMessageFor($failover?->lastError()?->lastException() ?? $exception)
                            .$this->exhaustedChainSuffix($failover),
                        'recoverable' => false,
                    ])."\n\n";
                    flush();

                    break;
                }
            }

            // A resume that died before its continuation produced anything
            // must not eat the user's decision: side-effect-free pendings
            // (questions) are restored so the cards return and the answer is
            // retryable; executed writes are left consumed to avoid doubling
            // the side effect.
            if ($failed && $resumeSnapshot !== null) {
                $this->compensateFailedResume($thread, $resumeSnapshot);
            }

            if ($attachmentIds !== null && $attachmentIds !== []) {
                $userMessageId = $thread->messages()->where('role', 'user')->orderByDesc('id')->value('id');

                // Only a new user message stored by this turn may receive the
                // attachments; otherwise (failed turn) they would be linked to
                // the previous turn's user message. Documents are thread
                // context, not message content, so only images are linked.
                if ($userMessageId !== null && $userMessageId !== $userMessageIdBefore) {
                    ChatAttachment::query()
                        ->whereIn('id', $attachmentIds)
                        ->images()
                        ->update(['message_id' => $userMessageId]);
                }
            }

            if ($reasoning !== '') {
                $this->storeReasoning($thread, $reasoning, $reasoningStartedAt, $assistantIdBefore);
            }

            if ($webCitations !== [] || $preSearchSources !== []) {
                $this->storeCitations($thread, $webCitations, $preSearchSources, $assistantIdBefore);
            }

            // A failed turn stored no new assistant message, so there is nothing
            // honest to announce and nothing to record it on.
            if (! $failed) {
                // The failover knows which attempt actually produced the stream,
                // the service snapshot covers a turn built without a guard.
                $providerName = $failover?->current()->name ?? $providerName;
                $providerModel = $failover?->current()->model ?? $providerModel;
                $fallbackUsed = $failover?->usedFallback() ?? $fallbackUsed;

                $this->storeTurnProvider($thread, $providerName, $providerModel, $fallbackUsed, $assistantIdBefore);

                echo 'data: '.json_encode([
                    'type' => 'meta',
                    'provider' => $providerName,
                    'fallback' => $fallbackUsed,
                ])."\n\n";
                flush();
            }

            echo "data: [DONE]\n\n";
            flush();

            $completed = true;
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function storeReasoning(ChatThread $thread, string $reasoning, ?float $startedAt, ?string $assistantIdBefore = null): void
    {
        $message = $thread->messages()->orderByDesc('id')->first();

        // Only a new assistant message stored by this turn may receive the
        // reasoning; otherwise (failed or approval-resume turn) it would be
        // attached to the previous turn's message.
        if (! $message instanceof ChatMessage || $message->role !== 'assistant' || $message->id === $assistantIdBefore) {
            return;
        }

        $meta = $message->meta ?? [];
        $meta['reasoning'] = [
            'text' => trim($reasoning),
            'duration_ms' => $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000),
        ];

        $message->update(['meta' => $meta]);
    }

    /**
     * Persist the turn's web sources (tool results plus the forced pre-search)
     * after any native provider citations, with the same guard as
     * storeReasoning: only a new assistant message stored by this turn may
     * receive them.
     *
     * @param  array<int, array{url: string, title: ?string, snippet: ?string}>  $webCitations
     * @param  array<int, array<string, mixed>>  $preSearchSources
     */
    protected function storeCitations(
        ChatThread $thread,
        array $webCitations,
        array $preSearchSources,
        ?string $assistantIdBefore = null,
    ): void {
        $message = $thread->messages()->orderByDesc('id')->first();

        if (! $message instanceof ChatMessage || $message->role !== 'assistant' || $message->id === $assistantIdBefore) {
            return;
        }

        $meta = $message->meta ?? [];
        $native = is_array($meta['citations'] ?? null) ? $meta['citations'] : [];
        $candidates = array_merge($webCitations, WebCitations::fromRows($preSearchSources));

        if (! WebCitations::hasNew($native, $candidates)) {
            return;
        }

        $meta['citations'] = WebCitations::merge($native, $candidates);

        $message->update(['meta' => $meta]);
    }

    /**
     * Record which provider answered the turn on the new assistant message.
     *
     * Guarded like storeReasoning: only a new assistant message stored by this
     * turn may receive it, and the other meta keys are preserved.
     *
     * @param  array{provider: ?string, model: ?string, fallback: bool}  $ai
     */
    protected function storeTurnProvider(
        ChatThread $thread,
        ?string $provider,
        ?string $model,
        bool $fallback,
        ?string $assistantIdBefore = null,
    ): void {
        $message = $thread->messages()->orderByDesc('id')->first();

        if (! $message instanceof ChatMessage || $message->role !== 'assistant' || $message->id === $assistantIdBefore) {
            return;
        }

        $meta = $message->meta ?? [];
        $meta['ai'] = ['provider' => $provider, 'model' => $model, 'fallback' => $fallback];

        $message->update(['meta' => $meta]);
    }

    /**
     * Honest note when the whole chain burned through: with a single provider
     * the message stays byte-identical to what it always was, because one
     * failing provider is not a chain worth reporting.
     */
    protected function exhaustedChainSuffix(?AiStreamFailover $failover): string
    {
        $lastError = $failover?->lastError();

        if ($lastError === null || count($lastError->errors()) < 2) {
            return '';
        }

        return sprintf(' (Fallaron %d proveedores.)', count($lastError->errors()));
    }

    /**
     * Roll a failed approval resume back to its pre-decision state when the
     * continuation never produced anything: the pending cards return so the
     * user can retry the decision. When the resume already executed a write,
     * the result stays in history (retrying would duplicate the side effect)
     * and only the decision stays consumed.
     *
     * @param  array{id: string, approval_state: ?array, tool_results: ?array}  $snapshot
     */
    protected function compensateFailedResume(ChatThread $thread, array $snapshot): void
    {
        $paused = $this->pausedApprovalCandidate($thread);

        if (! $paused instanceof ChatMessage || $paused->getKey() !== $snapshot['id']) {
            return;
        }

        $beforeIds = collect($snapshot['tool_results'] ?? [])->pluck('id')->all();

        $executedWrite = collect($paused->tool_results ?? [])
            ->reject(fn (mixed $result): bool => ! is_array($result) || in_array($result['id'] ?? null, $beforeIds, true))
            ->contains(fn (mixed $result): bool => ! ($result['denied'] ?? false));

        if ($executedWrite) {
            return;
        }

        $paused->forceFill([
            'approval_state' => $snapshot['approval_state'],
            'tool_results' => $snapshot['tool_results'],
        ])->save();
    }

    /**
     * Capture the rejected provider response for failed turns. The generic
     * RequestException only carries the status; the body explains the
     * rejection (model capability, payload shape, etc.).
     */
    protected function logProviderFailure(Throwable $exception, string $threadId): void
    {
        $exceptions = $exception instanceof AiAllProvidersFailedException
            ? array_values($exception->errors())
            : [$exception];

        foreach ($exceptions as $candidate) {
            if ($candidate instanceof RequestException) {
                Log::warning('ai.provider_request_failed', [
                    'thread' => $threadId,
                    'status' => $candidate->response?->status(),
                    'response' => substr((string) $candidate->response?->body(), 0, 6000),
                    'request' => substr((string) (self::$lastProviderRequest ?? ''), 0, 12000),
                ]);

                continue;
            }

            $previous = $candidate instanceof Throwable ? $candidate->getPrevious() : null;

            while ($previous !== null && ! $previous instanceof RequestException) {
                $previous = $previous->getPrevious();
            }

            if ($previous instanceof RequestException) {
                Log::warning('ai.provider_request_failed', [
                    'thread' => $threadId,
                    'status' => $previous->response?->status(),
                    'response' => substr((string) $previous->response?->body(), 0, 6000),
                    'request' => substr((string) (self::$lastProviderRequest ?? ''), 0, 12000),
                ]);
            }
        }
    }

    protected function errorMessageFor(Throwable $exception): string
    {
        $status = $exception instanceof RequestException
            ? $exception->response?->status()
            : null;

        return match (true) {
            // The chain carries the last real failure as `previous`, so the copy
            // for each status is exactly the one this method always produced.
            $exception instanceof AiAllProvidersFailedException => $this->errorMessageFor($exception->lastException()),
            $exception instanceof InsufficientCreditsException => 'Tu proveedor de IA no tiene saldo o cuota suficiente. Recarga tu cuenta o cambia la API key en Settings → IA.',
            $exception instanceof RateLimitedException => 'Tu proveedor está limitando las peticiones. Espera unos segundos e inténtalo de nuevo.',
            $exception instanceof ProviderOverloadedException => 'El proveedor de IA está sobrecargado. Inténtalo de nuevo en unos momentos.',
            $exception instanceof ProviderConnectionException => 'No se pudo conectar con tu proveedor de IA. Revisa la URL en Settings → IA.',
            in_array($status, [401, 403], true) => 'Tu API key fue rechazada. Revísala en Settings → IA.',
            $status === 404 => 'El endpoint o el modelo no existen. Revisa la URL y el modelo en Settings → IA.',
            $status === 400 => 'Tu proveedor rechazó la solicitud (400). Revisa el modelo configurado en Settings → IA.',
            default => 'La generación se interrumpió. Inténtalo de nuevo.',
        };
    }

    /**
     * The thread rail: the user's active threads that have messages, pinned
     * first and then most recent. A module narrows it to that module's own
     * threads; a null module is the general chat with every thread.
     *
     * @return Collection<int, ChatThread>
     */
    protected function threadsFor(User $user, ?string $module = null): Collection
    {
        $query = ChatThread::query()->forUser($user)->active();

        if ($module === ChatThread::MODULE_HEALTH) {
            // Health predates the `module` column, so its history lives in
            // `category = 'salud'`. Both spellings belong to the same rail:
            // filtering on one of them alone would split the history.
            $query->where(fn (Builder $query): Builder => $query
                ->category(ChatThread::CATEGORY_HEALTH)
                ->orWhere('module', ChatThread::MODULE_HEALTH));
        } elseif ($module !== null) {
            $query->where('module', $module);
        }

        return $query
            ->withMessages()
            ->ordered()
            ->limit(100)
            ->get();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function toolGroups(): array
    {
        return collect(ToolCatalog::groups())
            ->map(fn (array $group, string $key): array => ['key' => $key, 'label' => $group['label']])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, name: string}>
     */
    protected function agentsFor(User $user): array
    {
        return AgentDefinition::query()
            ->forUser($user)
            ->orderBy('name')
            ->get()
            ->map(fn (AgentDefinition $definition): array => [
                'key' => $definition->key,
                'name' => $definition->name,
            ])
            ->values()
            ->all();
    }
}
