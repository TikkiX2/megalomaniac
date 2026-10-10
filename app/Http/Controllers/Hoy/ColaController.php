<?php

namespace App\Http\Controllers\Hoy;

use App\Http\Controllers\Controller;
use App\Models\ColaMediaItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ColaController extends Controller
{
    public function index(Request $request)
    {
        $items = ColaMediaItem::where('user_id', $request->user()->id)->orderBy('posicion')->get();

        return Inertia::render('hoy/Cola', ['items' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:serie,pelicula,libro,juego'],
        ]);
        $max = ColaMediaItem::where('user_id', $request->user()->id)->max('posicion') ?? 0;
        ColaMediaItem::create([...$data, 'user_id' => $request->user()->id, 'posicion' => $max + 1]);

        return back()->with('success', 'Agregado.');
    }

    public function siguiente(Request $request)
    {
        $primero = ColaMediaItem::where('user_id', $request->user()->id)->orderBy('posicion')->first();
        $primero?->delete();

        return back()->with('success', 'Siguiente.');
    }

    public function reordenar(Request $request)
    {
        $data = $request->validate(['orden' => ['required', 'array'], 'orden.*' => ['integer']]);
        foreach ($data['orden'] as $i => $id) {
            ColaMediaItem::where('user_id', $request->user()->id)->whereKey($id)->update(['posicion' => $i + 1]);
        }

        return back()->with('success', 'Ordenado.');
    }

    public function destroy(Request $request, ColaMediaItem $item)
    {
        abort_if($item->user_id !== $request->user()->id, 403);
        $item->delete();

        return back()->with('success', 'Sacado.');
    }
}
