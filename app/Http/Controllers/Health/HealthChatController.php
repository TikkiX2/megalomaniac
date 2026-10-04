<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatThreadResource;
use App\Jobs\IndexChatDocument;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\HealthAppointment;
use App\Models\HealthCondition;
use App\Models\HealthStudy;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HealthChatController extends Controller
{
    public function __construct(protected ChatService $chat) {}

    public function index(Request $request): Response
    {
        $threads = ChatThread::query()
            ->forUser($request->user())
            ->category(ChatThread::CATEGORY_HEALTH)
            ->active()
            ->ordered()
            ->with('context')
            ->limit(100)
            ->get();

        return Inertia::render('health/chats/Index', [
            'threads' => ChatThreadResource::collection($threads)->resolve($request),
            'contextOptions' => [
                'conditions' => HealthCondition::where('user_id', $request->user()->id)
                    ->orderBy('name')->get(['id', 'name']),
                'studies' => HealthStudy::where('user_id', $request->user()->id)
                    ->orderBy('title')->get(['id', 'title']),
                'people' => Person::where('user_id', $request->user()->id)->visible()
                    ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'context_type' => ['nullable', 'in:health_condition,health_study,health_appointment,person', 'required_with:context_id'],
            'context_id' => ['nullable', 'integer', 'required_with:context_type'],
        ]);

        $context = match ($validated['context_type'] ?? null) {
            'health_condition' => HealthCondition::where('user_id', $request->user()->id)
                ->findOrFail($validated['context_id']),
            'health_study' => HealthStudy::where('user_id', $request->user()->id)
                ->findOrFail($validated['context_id']),
            'health_appointment' => HealthAppointment::where('user_id', $request->user()->id)
                ->findOrFail($validated['context_id']),
            'person' => Person::where('user_id', $request->user()->id)
                ->findOrFail($validated['context_id']),
            default => null,
        };

        $thread = $this->chat->createThread($request->user(), 'Consulta de salud');

        $thread->forceFill([
            'category' => ChatThread::CATEGORY_HEALTH,
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'tools_policy' => ['mode' => 'manual', 'groups' => ['health']],
        ])->save();

        // Los PDFs adjuntos al estudio se vinculan como fuentes del hilo para
        // que el asesor pueda leerlos (texto extraíble o vía OCR).
        if ($context instanceof HealthStudy) {
            foreach ($context->getMedia('attachments') as $media) {
                if (! str_starts_with((string) $media->mime_type, 'application/pdf')) {
                    continue;
                }

                try {
                    $contents = @file_get_contents($media->getPath());
                } catch (\Throwable) {
                    $contents = false;
                }

                if (! is_string($contents) || $contents === '') {
                    continue;
                }

                $storedPath = Storage::disk('local')->put(
                    'ai-attachments/'.$request->user()->id.'/'.Str::uuid7().'-'.$media->file_name,
                    $contents,
                );

                if ($storedPath === false) {
                    continue;
                }

                $attachment = ChatAttachment::create([
                    'id' => (string) Str::uuid7(),
                    'user_id' => $request->user()->id,
                    'thread_id' => null,
                    'kind' => 'document',
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $media->file_name,
                    'mime' => 'application/pdf',
                    'size' => (int) $media->size,
                    'status' => 'pending',
                ]);

                $thread->sources()->syncWithoutDetaching([$attachment->id]);
                IndexChatDocument::dispatch($attachment->id);
            }
        }

        return redirect()->route('ai.chat.show', $thread);
    }
}
