<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Models\QueueItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $items = QueueItem::where('user_id', $request->user()->id)->orderBy('position')->get();

        return Inertia::render('today/Cola', ['items' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', QueueItem::TYPES)],
        ]);
        $max = QueueItem::where('user_id', $request->user()->id)->max('position') ?? 0;
        QueueItem::create([...$data, 'user_id' => $request->user()->id, 'position' => $max + 1]);

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
