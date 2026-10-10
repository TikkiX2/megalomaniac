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
        return Inertia::render('today/BulkArchive', []);
    }

    public function preview(Request $request)
    {
        $data = $this->validateFilter($request);

        if ($data['filter'] === 'nunca') {
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

        if ($data['filter'] === 'nunca') {
            return back()->with('success', 'Nada para archivar.');
        }

        $query = $this->filteredTasks($request, $data);
        $ids = $query->pluck('id');
        $query->update(['archived_at' => now(), 'in_week' => false]);

        $request->session()->put('bulk_archive_undo', $ids->all());

        return back()->with('success', "Archivadas {$ids->count()} tareas.");
    }

    /**
     * Restaura exactamente los ids archivados en el último run y limpia la sesión.
     */
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
     * @param  array{filter: string, days?: int|null, project_id?: int|null}  $data
     */
    private function filteredTasks(Request $request, array $data): Builder
    {
        $query = ProjectTask::where('user_id', $request->user()->id)->notArchived()->notDone();
        if ($data['filter'] === 'older_than') {
            $query->where('created_at', '<', now()->subDays($data['days'] ?? 30));
        }
        if ($data['filter'] === 'project' && ! empty($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        }

        return $query;
    }

    /**
     * @return array{filter: string, days?: int|null, project_id?: int|null}
     */
    private function validateFilter(Request $request, bool $confirm = false): array
    {
        return $request->validate([
            'filter' => ['required', 'in:nunca,older_than,project,all'],
            'days' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'confirmed' => $confirm ? ['required', 'accepted'] : ['nullable'],
        ]);
    }
}
