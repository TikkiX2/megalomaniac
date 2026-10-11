<?php

namespace App\Services\Today;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskSavedView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TaskFilterService
{
    public const FILTER_KEYS = ['project_id', 'priority', 'status', 'tag', 'type'];

    public const PRIORITIES = ['Low', 'Normal', 'High', 'Urgent'];

    public const STATUSES = ['Pending', 'In Progress'];

    public const TYPES = ['personal', 'freelance'];

    public const VIEW_TYPES = ['week' => 'today_week', 'tomorrow' => 'today_tomorrow'];

    /** Nombres de la vista reservada por sección (spec §3.4). */
    public const VIEW_NAMES = ['week' => 'Semana (automática)', 'tomorrow' => 'Mañana (automática)'];

    /**
     * Devuelve los filtros activos de la sección: si el request trae claves de filtro,
     * los sanitiza y los recuerda; si no, lee la memoria previa (sanitizándola);
     * con cleared=1 borra la memoria.
     */
    public function resolve(Request $request, string $section): array
    {
        $userId = (int) $request->user()->id;

        if ($request->boolean('cleared')) {
            $this->forget($userId, $section);

            return [];
        }

        $incoming = [];
        foreach (self::FILTER_KEYS as $key) {
            if ($request->has($key)) {
                $incoming[$key] = $request->input($key);
            }
        }

        if ($incoming !== []) {
            $filters = $this->sanitize($userId, $incoming);
            $this->remember($userId, $section, $filters);

            return $filters;
        }

        $stored = TaskSavedView::where('user_id', $userId)
            ->where('view_type', self::VIEW_TYPES[$section])
            ->first();

        if ($stored === null) {
            return [];
        }

        $sanitized = $this->sanitize($userId, $stored->filters ?? []);
        if ($sanitized !== ($stored->filters ?? [])) {
            $this->remember($userId, $section, $sanitized);
        }

        return $sanitized;
    }

    /**
     * Mantiene solo claves válidas: project_id propio y no borrado, priority/status/type
     * dentro de sus enums y tag como string no vacío de hasta 64 caracteres.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitize(int $userId, array $input): array
    {
        $filters = [];

        $projectId = $input['project_id'] ?? null;
        if (is_numeric($projectId) && Project::where('user_id', $userId)->whereKey((int) $projectId)->exists()) {
            $filters['project_id'] = (int) $projectId;
        }

        if (in_array($input['priority'] ?? null, self::PRIORITIES, true)) {
            $filters['priority'] = $input['priority'];
        }

        if (in_array($input['status'] ?? null, self::STATUSES, true)) {
            $filters['status'] = $input['status'];
        }

        $tag = $input['tag'] ?? null;
        if (is_string($tag) && trim($tag) !== '' && strlen(trim($tag)) <= 64) {
            $filters['tag'] = trim($tag);
        }

        if (in_array($input['type'] ?? null, self::TYPES, true)) {
            $filters['type'] = $input['type'];
        }

        return $filters;
    }

    /**
     * Persiste los filtros en la vista reservada de la sección; si quedan vacíos, borra la fila.
     *
     * @param  array<string, mixed>  $filters
     */
    public function remember(int $userId, string $section, array $filters): void
    {
        if ($filters === []) {
            $this->forget($userId, $section);

            return;
        }

        TaskSavedView::updateOrCreate(
            ['user_id' => $userId, 'view_type' => self::VIEW_TYPES[$section]],
            ['name' => self::VIEW_NAMES[$section], 'filters' => $filters],
        );
    }

    public function forget(int $userId, string $section): void
    {
        TaskSavedView::where('user_id', $userId)
            ->where('view_type', self::VIEW_TYPES[$section])
            ->delete();
    }

    /**
     * Opciones para los selects de filtros: proyectos propios no borrados y tags
     * distintos de las tareas pendientes del usuario.
     *
     * @return array{projects: Collection, tags: array<int, string>}
     */
    public function options(int $userId): array
    {
        $projects = Project::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $tags = ProjectTask::where('user_id', $userId)
            ->notDone()
            ->notArchived()
            ->get(['tags'])
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return ['projects' => $projects, 'tags' => $tags];
    }
}
