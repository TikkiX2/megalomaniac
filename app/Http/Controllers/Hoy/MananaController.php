<?php

namespace App\Http\Controllers\Hoy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hoy\StoreMananaRequest;
use App\Models\Dia;
use App\Models\DiaItem;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MananaController extends Controller
{
    public function show(Request $request)
    {
        $userId = $request->user()->id;
        $manana = now()->addDay()->toDateString();
        $ayer = now()->subDay()->toDateString();

        $pool = ProjectTask::where('user_id', $userId)
            ->enSemana()
            ->orderBy('sort_order')
            ->limit(10)
            ->get(['id', 'title']);

        $pendientesAyer = DiaItem::whereHas('dia', fn ($q) => $q->where('user_id', $userId)->where('fecha', $ayer))
            ->where('estado', 'pendiente')
            ->with('dia')
            ->get();

        $diaManana = Dia::with('itemsVisibles')
            ->where('user_id', $userId)
            ->where('fecha', $manana)
            ->first();

        return Inertia::render('hoy/Manana', [
            'fecha' => $manana,
            'pool' => $pool,
            'pendientesAyer' => $pendientesAyer,
            'diaManana' => $diaManana,
        ]);
    }

    public function store(StoreMananaRequest $request)
    {
        $userId = $request->user()->id;
        $manana = now()->addDay()->toDateString();
        $items = $request->validated()['items'] ?? [];

        // Validar tarea_id pertenece al usuario
        foreach ($items as $it) {
            if (! empty($it['tarea_id'])) {
                $t = ProjectTask::where('id', $it['tarea_id'])->where('user_id', $userId)->first();
                if (! $t || $t->archivada_at) {
                    abort(422, 'Tarea inválida.');
                }
            }
        }

        $dia = Dia::firstOrCreate(['user_id' => $userId, 'fecha' => $manana]);
        // Reemplaza visibles (soltados quedan como rastro invisible, no cuentan)
        $dia->items()->where('estado', '!=', 'soltado')->delete();
        foreach ($items as $it) {
            $dia->items()->create([
                'tarea_id' => $it['tarea_id'] ?? null,
                'titulo' => $it['titulo'],
                'ancla' => $it['ancla'],
                'posicion' => $it['posicion'],
                'estado' => 'pendiente',
            ]);
        }

        // "Dejarlo vacío": items vacío deja el día vacío sin fricción.
        return redirect()->route('dashboard')->with('success', 'Mañana listo.');
    }

    public function ponerHoy(Request $request, DiaItem $item)
    {
        abort_if($item->dia->user_id !== $request->user()->id, 403);
        $hoy = now()->toDateString();
        $diaHoy = Dia::firstOrCreate(['user_id' => $request->user()->id, 'fecha' => $hoy]);

        $visibles = $diaHoy->itemsVisibles()->count();
        if ($visibles >= 3) {
            abort(422, 'Máximo 3 ítems por día.');
        }
        $pos = $diaHoy->itemsVisibles()->max('posicion') + 1;
        if ($pos < 1 || $pos > 3) {
            $pos = $visibles + 1;
        }

        $diaHoy->items()->create([
            'tarea_id' => $item->tarea_id,
            'titulo' => $item->titulo,
            'ancla' => $item->ancla,
            'posicion' => $pos,
            'estado' => 'pendiente',
        ]);
        $item->update(['estado' => 'soltado']);

        return back()->with('success', 'Puesto hoy.');
    }
}
