<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\EditChatMessageRequest;
use App\Http\Requests\Ai\SendChatMessageRequest;
use App\Http\Requests\Ai\UpdateChatThreadRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
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
                $thread->messages()->orderBy('id')->get()
            )->resolve($request),
            'threads' => ChatThreadResource::collection($this->threadsFor($user))->resolve($request),
            'models' => $this->service->availableModels($user),
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

        if ($threadId !== null) {
            $thread = ChatThread::query()->forUser($user)->find($threadId);

            abort_if($thread === null, 404);

            $this->authorize('update', $thread);
        } else {
            $thread = $this->service->createThread($user, $request->validated('message'), $model);
        }

        if ($model !== null) {
            $thread->update(['model' => $model]);
        }

        return $this->streamResponse(
            $this->service->streamTurn($user, $thread, $request->validated('message'), $model ?? $thread->model),
            $thread,
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

        return $this->streamResponse($stream, $thread);
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

        return $this->streamResponse($stream, $thread);
    }

    protected function streamResponse(StreamableAgentResponse $stream, ChatThread $thread): StreamedResponse
    {
        return response()->stream(function () use ($stream, $thread): void {
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }

            echo 'data: '.json_encode(['type' => 'thread', 'threadId' => $thread->id])."\n\n";
            flush();

            try {
                foreach ($stream as $event) {
                    echo 'data: '.((string) $event)."\n\n";
                    flush();
                }
            } catch (Throwable $exception) {
                report($exception);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'La generación se interrumpió. Inténtalo de nuevo.',
                    'recoverable' => false,
                ])."\n\n";
                flush();
            }

            echo "data: [DONE]\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
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
}
