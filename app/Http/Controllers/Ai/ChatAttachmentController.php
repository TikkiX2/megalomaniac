<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StoreChatAttachmentRequest;
use App\Http\Resources\ChatAttachmentResource;
use App\Jobs\IndexChatDocument;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatAttachmentController extends Controller
{
    public function store(StoreChatAttachmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

        $threadId = $request->validated('thread_id');

        if ($threadId !== null) {
            ChatThread::query()->forUser($user)->findOrFail($threadId);
        }

        $attachment = ChatAttachment::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->getKey(),
            'thread_id' => $threadId,
            'kind' => $isImage ? 'image' : 'document',
            'disk' => 'local',
            'path' => (string) $file->store('ai-attachments/'.$user->id, 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'status' => $isImage ? 'ready' : 'pending',
        ]);

        if (! $isImage) {
            IndexChatDocument::dispatch($attachment->id);
        }

        return response()->json(
            (new ChatAttachmentResource($attachment))->resolve($request),
            201,
        );
    }

    /**
     * @return AnonymousResourceCollection<int, ChatAttachmentResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:25'],
            'ids.*' => ['string', 'size:36'],
        ]);

        return ChatAttachmentResource::collection(
            ChatAttachment::query()
                ->forUser($request->user())
                ->whereIn('id', $data['ids'])
                ->get()
        );
    }

    public function show(ChatAttachment $attachment): BinaryFileResponse
    {
        $this->authorize('view', $attachment);

        return response()->file(
            Storage::disk($attachment->disk)->path($attachment->path),
            ['Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"'],
        );
    }

    public function destroy(ChatAttachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $attachment);

        if ($attachment->kind === 'image' && $attachment->message_id !== null) {
            abort(422, 'No se puede eliminar una imagen ya enviada.');
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back();
    }
}
