<?php

namespace App\Http\Controllers\Hoy;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArchivoController extends Controller
{
    public function index(Request $request)
    {
        $items = ProjectTask::where('user_id', $request->user()->id)
            ->archivadas()
            ->orderByDesc('archivada_at')
            ->limit(100)
            ->get(['id', 'title', 'archivada_at']);

        return Inertia::render('hoy/Archivadas', ['items' => $items]);
    }

    public function restaurar(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        ProjectTask::where('user_id', $request->user()->id)->whereIn('id', $data['ids'])->update(['archivada_at' => null]);

        return back()->with('success', 'Restauradas.');
    }

    public function masivo(Request $request)
    {
        return Inertia::render('hoy/ArchivoMasivo', []);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'filtro' => ['required', 'in:nunca,mas_n_dias,proyecto,todas'],
            'dias' => ['nullable', 'integer', 'min:1'],
            'proyecto_id' => ['nullable', 'exists:projects,id'],
        ]);

        $query = ProjectTask::where('user_id', $request->user()->id)->noArchivadas()->where('is_done', false);
        if ($data['filtro'] === 'mas_n_dias') {
            $query->where('created_at', '<', now()->subDays($data['dias'] ?? 30));
        }
        if ($data['filtro'] === 'proyecto' && ! empty($data['proyecto_id'])) {
            $query->where('project_id', $data['proyecto_id']);
        }
        if ($data['filtro'] === 'nunca') {
            $query->where('id', '<', 0);
        }

        $total = $query->count();
        $primeros = $query->orderBy('id')->limit(15)->pluck('title');

        return response()->json(['total' => $total, 'primeros' => $primeros]);
    }

    public function ejecutar(Request $request)
    {
        $data = $request->validate([
            'filtro' => ['required', 'in:nunca,mas_n_dias,proyecto,todas'],
            'dias' => ['nullable', 'integer', 'min:1'],
            'proyecto_id' => ['nullable', 'exists:projects,id'],
            'confirmado' => ['required', 'accepted'],
        ]);

        $query = ProjectTask::where('user_id', $request->user()->id)->noArchivadas()->where('is_done', false);
        if ($data['filtro'] === 'mas_n_dias') {
            $query->where('created_at', '<', now()->subDays($data['dias'] ?? 30));
        }
        if ($data['filtro'] === 'proyecto' && ! empty($data['proyecto_id'])) {
            $query->where('project_id', $data['proyecto_id']);
        }
        if ($data['filtro'] === 'nunca') {
            return back()->with('success', 'Nada para archivar.');
        }

        $n = $query->count();
        $query->update(['archivada_at' => now(), 'en_semana' => false]);

        return back()->with('success', "Archivadas {$n} tareas. Podés deshacer desde Archivadas.");
    }
}
