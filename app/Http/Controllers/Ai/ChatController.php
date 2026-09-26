<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Services\ChatService;
use App\Ai\Tools\ToolCatalog;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    public function __construct(protected ChatService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('ai/chat', [
            'threads' => ChatThreadResource::collection($this->threadsFor($user))->resolve($request),
            'models' => $this->service->availableModels($user),
            'agents' => $this->agentsFor($user),
            'toolGroups' => $this->toolGroups(),
            'ai' => $this->aiState($user),
        ]);
    }

    public function show(Request $request, ChatThread $thread): Response
    {
        $this->authorize('view', $thread);

        $user = $request->user();

        return Inertia::render('ai/thread', [
            'thread' => (new ChatThreadResource($thread))->resolve($request),
            'messages' => ChatMessageResource::collection(
                $thread->messages()->with('attachments')->orderBy('id')->get()
            )->resolve($request),
            'documents' => ChatAttachmentResource::collection(
                $thread->attachments()
                    ->where('kind', 'document')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get()
            )->resolve($request),
            'threads' => ChatThreadResource::collection($this->threadsFor($user))->resolve($request),
            'models' => $this->service->availableModels($user),
            'agents' => $this->agentsFor($user),
            'toolGroups' => $this->toolGroups(),
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

        if (array_key_exists('tools_policy', $data)) {
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
            $thread = $this->service->createThread($user, $request->validated('message'), $model, (string) ($request->validated('agent') ?? 'megalomaniac'));

            // Documents uploaded from the chat home have no thread yet; scope
            // them to the freshly created thread so their context is injected.
            ChatAttachment::query()
                ->whereIn('id', $attachmentIds ?? [])
                ->where('user_id', $user->getKey())
                ->whereNull('thread_id')
                ->update(['thread_id' => $thread->id]);
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
        );

        return $this->streamResponse($stream, $thread, $this->service->lastToolPolicy, $attachmentIds);
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

        $decisions = Decisions::from(collect($request->validated('decisions'))
            ->map(function (array|bool $decision): Decision|bool {
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

        return $this->streamResponse($stream, $thread);
    }

    /**
     * @param  array{mode?: string, groups?: string[]}  $toolPolicy
     * @param  array<int, string>|null  $attachmentIds
     */
    protected function streamResponse(
        StreamableAgentResponse $stream,
        ChatThread $thread,
        array $toolPolicy = [],
        ?array $attachmentIds = null,
    ): StreamedResponse {
        return response()->stream(function () use ($stream, $thread, $toolPolicy, $attachmentIds): void {
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }

            echo 'data: '.json_encode(['type' => 'thread', 'threadId' => $thread->id])."\n\n";
            flush();

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
                }
            } catch (Throwable $exception) {
                report($exception);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => $this->errorMessageFor($exception),
                    'recoverable' => false,
                ])."\n\n";
                flush();
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

            echo "data: [DONE]\n\n";
            flush();
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

    protected function errorMessageFor(Throwable $exception): string
    {
        $status = $exception instanceof RequestException
            ? $exception->response?->status()
            : null;

        return match (true) {
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
     * @return Collection<int, ChatThread>
     */
    protected function threadsFor(User $user): Collection
    {
        return ChatThread::query()
            ->forUser($user)
            ->active()
            ->withMessages()
            ->ordered()
            ->limit(100)
            ->get();
    }

    /**
     * @return array{enabled: bool, configured: bool, defaultModel: ?string}
     */
    protected function aiState(User $user): array
    {
        return [
            'enabled' => (bool) $user->ai_enabled,
            'configured' => $this->service->isConfigured($user),
            'defaultModel' => $user->ai_model ?: null,
        ];
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
