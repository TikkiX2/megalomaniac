<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Http\Requests\Today\StoreQueueItemRequest;
use App\Models\QueueItem;
use App\Services\Media\MediaSearchResult;
use App\Services\Media\MediaSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $items = QueueItem::where('user_id', $request->user()->id)->orderBy('position')->get();

        return Inertia::render('today/Queue', ['items' => $items]);
    }

    /**
     * Búsqueda externa keyless (wikidata/openlibrary/musicbrainz).
     */
    public function search(Request $request, MediaSearchService $search)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', QueueItem::TYPES)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $results = $search->search($data['type'], (string) ($data['q'] ?? ''));

        return response()->json(array_map(
            fn (MediaSearchResult $result): array => $result->toArray(),
            $results,
        ));
    }

    public function store(StoreQueueItemRequest $request)
    {
        $userId = $request->user()->id;
        $data = $request->validated();

        if (filled($data['source'] ?? null) && filled($data['external_id'] ?? null)) {
            $alreadyQueued = QueueItem::where('user_id', $userId)
                ->where('source', $data['source'])
                ->where('external_id', $data['external_id'])
                ->exists();

            if ($alreadyQueued) {
                return back()->with('success', 'Ya estaba en la cola.');
            }
        }

        $max = QueueItem::where('user_id', $userId)->max('position') ?? 0;
        QueueItem::create([
            'user_id' => $userId,
            'title' => $data['title'],
            'type' => $data['type'],
            'position' => $max + 1,
            'source' => $data['source'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'year' => $data['year'] ?? null,
            'creator' => $data['creator'] ?? null,
        ]);

        return back()->with('success', 'Agregado.');
    }

    public function next(Request $request)
    {
        $first = QueueItem::where('user_id', $request->user()->id)->orderBy('position')->first();
        $first?->delete();

        return back()->with('success', 'Siguiente.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['orden' => ['required', 'array'], 'orden.*' => ['integer']]);
        foreach ($data['orden'] as $i => $id) {
            QueueItem::where('user_id', $request->user()->id)->whereKey($id)->update(['position' => $i + 1]);
        }

        return back()->with('success', 'Ordenado.');
    }

    public function destroy(Request $request, QueueItem $item)
    {
        abort_if($item->user_id !== $request->user()->id, 403);
        $item->delete();

        return back()->with('success', 'Sacado.');
    }
}
