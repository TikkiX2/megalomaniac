<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\UpdateChatThreadRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
