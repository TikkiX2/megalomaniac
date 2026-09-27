<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\StoreMemoryRequest;
use App\Http\Requests\Memories\UpdateMemoryRequest;
use App\Models\ChatThread;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemoryController extends Controller
{
    public function __construct(protected MemoryCatalog $catalog) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $threads = ChatThread::query()
            ->forUser($user)
            ->active()
            ->withMessages()
            ->ordered()
            ->get(['id', 'title']);

        $selected = $request->query('thread');
        $selectedThread = is_string($selected) ? $threads->firstWhere('id', $selected)?->id : null;

        return Inertia::render('ai/memory', [
            'memories' => Memory::query()
                ->forUser($user)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Memory $memory): array => [
                    'id' => $memory->id,
                    'scope' => $memory->scope->value,
                    'thread_id' => $memory->thread_id,
                    'content' => $memory->content,
                    'source' => $memory->source,
                    'updated_at' => $memory->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'threads' => $threads->map(fn (ChatThread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
            ])->values()->all(),
            'limits' => [
                'max_content' => MemoryCatalog::MAX_CONTENT,
                'max_global' => MemoryCatalog::MAX_GLOBAL,
                'max_thread' => MemoryCatalog::MAX_THREAD,
            ],
            'selected_thread' => $selectedThread,
        ]);
    }

    public function store(StoreMemoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $scope = MemoryScope::from($data['scope']);

        $thread = $scope === MemoryScope::Thread
            ? ChatThread::query()->forUser($request->user())->findOrFail($data['thread_id'])
            : null;

        $this->catalog->remember($request->user(), $data['content'], $scope, $thread, source: 'user');

        return back()->with('success', 'Memoria guardada.');
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $this->catalog->update($memory, $request->validated()['content']);

        return back()->with('success', 'Memoria actualizada.');
    }

    public function destroy(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('delete', $memory);

        $this->catalog->forget($memory);

        return back()->with('success', 'Memoria eliminada.');
    }

    public function promote(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $this->catalog->promote($memory);

        return back()->with('success', 'Memoria promovida a general.');
    }
}
