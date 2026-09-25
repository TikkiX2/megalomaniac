<?php

namespace App\Ai\Tools;

use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s tasks (personal and freelance project tasks): pending, overdue, due soon, by project, status or priority, with search. Use this before answering anything about tasks, backlog, deadlines or pending work.';
    }

    public function handle(Request $request): Stringable|string
    {
        $scope = (string) ($request['scope'] ?? 'all');
        $base = ProjectTask::query()
            ->where('user_id', $this->user->id)
            ->with('project:id,name');

        if ($scope === 'personal') {
            $base->whereNull('project_id');
        } elseif ($scope === 'freelance') {
            $base->whereNotNull('project_id');
        }

        $query = (clone $base);

        if (! ($request['include_done'] ?? false)) {
            $query->where('is_done', false)->where('is_archived', false);
        }

        if ($status = $request['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($priority = $request['priority'] ?? null) {
            $query->where('priority', $priority);
        }

        if ($request['overdue'] ?? false) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->where('is_done', false);
        }

        if ($days = $request['due_within_days'] ?? null) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '>=', now()->toDateString())
                ->whereDate('due_date', '<=', now()->addDays((int) $days)->toDateString());
        }

        if ($dueBefore = $request['due_before'] ?? null) {
            $query->whereNotNull('due_date')->whereDate('due_date', '<=', $dueBefore);
        }

        if ($project = $request['project'] ?? null) {
            $query->whereHas('project', function ($relation) use ($project): void {
                $relation->where('name', 'like', "%{$project}%");

                if (is_numeric($project)) {
                    $relation->orWhere('id', (int) $project);
                }
            });
        }

        if ($search = $request['search'] ?? null) {
            $query->where('title', 'like', '%'.addcslashes((string) $search, '%_').'%');
        }

        $limit = min(50, max(1, (int) ($request['limit'] ?? 20)));

        $tasks = $query
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ProjectTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
                'is_done' => $task->is_done,
                'project' => $task->project?->name,
                'scope' => $task->project_id === null ? 'personal' : 'freelance',
            ])
            ->values()
            ->all();

        return json_encode([
            'tasks' => $tasks,
            'summary' => [
                'pending' => (clone $base)->where('is_done', false)->where('is_archived', false)->count(),
                'overdue' => (clone $base)->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->where('is_done', false)
                    ->count(),
                'due_today' => (clone $base)->whereDate('due_date', now()->toDateString())
                    ->where('is_done', false)
                    ->count(),
            ],
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()
                ->enum(['all', 'personal', 'freelance'])
                ->description('Limitar a tareas personales o de proyectos freelance'),
            'status' => $schema->string()->description('Estado exacto, p. ej. Pending, In Progress, To Do, Done'),
            'priority' => $schema->string()->description('Prioridad: Low, Normal, High, Urgent'),
            'overdue' => $schema->boolean()->description('Solo vencidas'),
            'due_within_days' => $schema->integer()->description('Vencen dentro de N días'),
            'due_before' => $schema->string()->description('Vencen antes de la fecha (YYYY-MM-DD)'),
            'project' => $schema->string()->description('Nombre o ID del proyecto'),
            'search' => $schema->string()->description('Buscar en el título'),
            'include_done' => $schema->boolean()->description('Incluir completadas')->default(false),
            'limit' => $schema->integer()->description('Máximo de tareas (1-50)')->default(20),
        ];
    }
}
