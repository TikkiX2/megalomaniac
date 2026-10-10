<?php

namespace App\Http\Controllers\Today;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArchiveBulkController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('today/ArchivoMasivo', []);
    }

    public function preview(Request $request)
    {
        $data = $this->validateFilter($request);

        if ($data['filtro'] === 'nunca') {
            return response()->json(['total' => 0, 'primeros' => []]);
        }

        $query = $this->filteredTasks($request, $data);

        return response()->json([
            'total' => $query->count(),
            'primeros' => $query->orderBy('id')->limit(15)->pluck('title'),
        ]);
    }

    public function run(Request $request)
    {
        $data = $this->validateFilter($request, confirm: true);

        if ($data['filtro'] === 'nunca') {
            return back()->with('success', 'Nada para archivar.');
        }

        $query = $this->filteredTasks($request, $data);
        $ids = $query->pluck('id');
        $query->update(['archived_at' => now(), 'in_week' => false]);

        $request->session()->put('bulk_archive_undo', $ids->all());

        return back()->with('success', "Archivadas {$ids->count()} tareas.");
    }

    public function undo(Request $request)
    {
        $ids = $request->session()->get('bulk_archive_undo', []);
        if ($ids !== []) {
            ProjectTask::where('user_id', $request->user()->id)->whereIn('id', $ids)->update(['archived_at' => null]);
            $request->session()->forget('bulk_archive_undo');
        }

        return back()->with('success', 'Deshecho.');
    }

    /**
     * @param  array{filtro: string, dias?: int|null, proyecto_id?: int|null}  $data
     */
    private function filteredTasks(Request $request, array $data): Builder
    {
        $query = ProjectTask::where('user_id', $request->user()->id)->notArchived()->notDone();
        if ($data['filtro'] === 'mas_n_dias') {
            $query->where('created_at', '<', now()->subDays($data['dias'] ?? 30));
        }
        if ($data['filtro'] === 'proyecto' && ! empty($data['proyecto_id'])) {
            $query->where('project_id', $data['proyecto_id']);
        }

        return $query;
    }

    /**
     * @return array{filtro: string, dias?: int|null, proyecto_id?: int|null}
     */
    private function validateFilter(Request $request, bool $confirm = false): array
    {
        return $request->validate([
            'filtro' => ['required', 'in:nunca,mas_n_dias,proyecto,todas'],
            'dias' => ['nullable', 'integer', 'min:1'],
            'proyecto_id' => ['nullable', 'exists:projects,id'],
            'confirmado' => $confirm ? ['required', 'accepted'] : ['nullable'],
        ]);
    }
}
