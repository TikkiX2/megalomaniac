<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Models\HealthAppointment;
use App\Models\HealthCondition;
use App\Models\HealthStudy;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()->route('ai.chat.show', $thread);
    }
}
