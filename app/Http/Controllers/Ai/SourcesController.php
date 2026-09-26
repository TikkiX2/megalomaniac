<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatAttachmentResource;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SourcesController extends Controller
{
    public function __construct(protected ChatService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('ai/sources', [
            'documents' => ChatAttachmentResource::collection(
                ChatAttachment::query()
                    ->forUser($user)
                    ->documents()
                    ->with(['threads' => fn ($query) => $query->orderByDesc('chat_thread_sources.created_at')])
                    ->withCount('threads')
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get()
            )->resolve($request),
            'ai' => $this->aiState($user),
        ]);
    }

    public function attach(Request $request, ChatThread $thread): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $thread);

        $data = $request->validate([
            'attachment_id' => ['required', 'string', 'size:36'],
        ]);

        $attachment = ChatAttachment::query()
            ->forUser($request->user())
            ->documents()
            ->whereIn('status', ['pending', 'indexed'])
            ->find($data['attachment_id']);

        if ($attachment === null) {
            throw ValidationException::withMessages([
                'attachment_id' => 'La fuente no existe o no está lista para adjuntar.',
            ]);
        }

        if ($thread->sources()->whereKey($attachment->id)->exists()) {
            throw ValidationException::withMessages([
                'attachment_id' => 'La fuente ya está adjunta a este hilo.',
            ]);
        }

        $thread->sources()->syncWithoutDetaching([$attachment->id]);

        return $request->expectsJson()
            ? response()->json(['attachment_id' => $attachment->id], 201)
            : back();
    }

    public function detach(Request $request, ChatThread $thread, ChatAttachment $attachment): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $thread);

        abort_unless($thread->sources()->whereKey($attachment->id)->exists(), 404);

        $thread->sources()->detach($attachment->id);

        return $request->expectsJson() ? response()->noContent() : back();
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
