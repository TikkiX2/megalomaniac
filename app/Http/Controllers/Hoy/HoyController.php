<?php

namespace App\Http\Controllers\Hoy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hoy\UpdateDiaItemRequest;
use App\Models\Bloque;
use App\Models\ColaMediaItem;
use App\Models\Dia;
use App\Models\DiaItem;
use App\Models\Routine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HoyController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $hoy = now()->toDateString();

        $dia = Dia::with(['itemsVisibles.tarea'])
            ->where('user_id', $userId)
            ->where('fecha', $hoy)
            ->first();

        $bloque = Bloque::where('user_id', $userId)
            ->where('dia_semana', now()->dayOfWeek)
            ->where('activo', true)
            ->orderBy('hora_inicio')
            ->first();

        $rutina = Routine::where('user_id', $userId)
            ->where('scheduled_date', now()->format('l'))
            ->first();

        $colaPrimero = ColaMediaItem::where('user_id', $userId)
            ->orderBy('posicion')
            ->first();

        return Inertia::render('hoy/Index', [
            'fecha' => $hoy,
            'dia' => $dia,
            'bloque' => $bloque,
            'rutina' => $rutina ? ['id' => $rutina->id, 'name' => $rutina->name, 'focus' => $rutina->focus] : null,
            'colaPrimero' => $colaPrimero,
        ]);
    }

    public function update(UpdateDiaItemRequest $request, DiaItem $item)
    {
        $validated = $request->validated();
        $item->update([
            'estado' => $validated['estado'],
            'nota_cierre' => $validated['nota_cierre'] ?? null,
            'hecho_at' => $validated['estado'] === 'hecho' ? now() : null,
        ]);

        return back()->with('success', 'Listo.');
    }

    public function soltar(Request $request, DiaItem $item)
    {
        abort_if($item->dia->user_id !== $request->user()->id, 403);
        $item->update(['estado' => 'soltado']);

        return back()->with('success', 'Soltado.');
    }
}
