<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Support\ChatImageOptimizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StoreChatAttachmentRequest;
use App\Http\Resources\ChatAttachmentResource;
use App\Jobs\IndexChatDocument;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatAttachmentController extends Controller
{
    public function __construct(protected ChatImageOptimizer $optimizer) {}

    public function store(StoreChatAttachmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

        $threadId = $request->validated('thread_id');

        $thread = $threadId === null
            ? null
            : ChatThread::query()->forUser($user)->findOrFail($threadId);

        $stored = $isImage
            ? $this->storeImage($file, $user)
            : [
                'path' => (string) $file->store('ai-attachments/'.$user->id, 'local'),
                'size' => (int) $file->getSize(),
                'mime' => (string) $file->getMimeType(),
            ];

        $attachment = ChatAttachment::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->getKey(),
            // Only images keep the legacy thread column (they are
            // message-scoped); documents live in the library pivot instead.
            'thread_id' => $isImage ? $threadId : null,
            'kind' => $isImage ? 'image' : 'document',
            'disk' => 'local',
            'path' => $stored['path'],
            'original_name' => $file->getClientOriginalName(),
            'mime' => $stored['mime'],
            'size' => $stored['size'],
            'status' => $isImage ? 'ready' : 'pending',
        ]);

        if (! $isImage) {
            $thread?->sources()->syncWithoutDetaching([$attachment->id]);

            IndexChatDocument::dispatch($attachment->id);
        }

        return response()->json(
            (new ChatAttachmentResource($attachment))->resolve($request),
            201,
        );
    }

    /**
     * Store an image downscaled when possible so the conversation history
     * re-embedded on every turn stays small. Falls back to the original upload
     * when the optimizer cannot handle the file.
     *
     * @return array{path: string, size: int, mime: string}
     */
    protected function storeImage(UploadedFile $file, User $user): array
    {
        $optimized = $this->optimizer->optimize((string) $file->getRealPath());

        if ($optimized !== null) {
            $path = 'ai-attachments/'.$user->id.'/'.Str::uuid()->toString().'.'.$optimized['extension'];

            Storage::disk('local')->put($path, $optimized['contents']);

            return ['path' => $path, 'size' => $optimized['size'], 'mime' => $optimized['mime']];
        }

        return [
            'path' => (string) $file->store('ai-attachments/'.$user->id, 'local'),
            'size' => (int) $file->getSize(),
            'mime' => (string) $file->getMimeType(),
        ];
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
