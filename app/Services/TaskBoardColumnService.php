<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskBoardColumn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskBoardColumnService
{
    /** @var array<int, array{key: string, label: string, color: string, is_done: bool}> */
    public const PERSONAL_DEFAULTS = [
        ['key' => 'Pending', 'label' => 'Pendiente', 'color' => 'amber', 'is_done' => false],
        ['key' => 'In Progress', 'label' => 'En Progreso', 'color' => 'primary', 'is_done' => false],
        ['key' => 'Done', 'label' => 'Hecho', 'color' => 'emerald', 'is_done' => true],
    ];

    /** @var array<int, array{key: string, label: string, color: string, is_done: bool}> */
    public const FREELANCE_DEFAULTS = [
        ['key' => 'To Do', 'label' => 'Pendiente', 'color' => 'amber', 'is_done' => false],
        ['key' => 'In Progress', 'label' => 'En Progreso', 'color' => 'primary', 'is_done' => false],
        ['key' => 'Done', 'label' => 'Completada', 'color' => 'emerald', 'is_done' => true],
    ];

    /**
     * @return Collection<int, TaskBoardColumn>
     */
    public static function defaultColumnsFor(User $user): Collection
    {
        $existing = TaskBoardColumn::query()
            ->where('user_id', $user->id)
            ->whereNull('project_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        self::insertDefaults($user->id, null, self::PERSONAL_DEFAULTS);

        return TaskBoardColumn::query()
            ->where('user_id', $user->id)
            ->whereNull('project_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public static function seedFor(Project $project): void
    {
        if ($project->boardColumns()->exists()) {
            return;
        }

        $defaults = $project->type === 'personal' ? self::PERSONAL_DEFAULTS : self::FREELANCE_DEFAULTS;

        self::insertDefaults($project->user_id, $project->id, $defaults);
    }

    /**
     * @return Collection<int, TaskBoardColumn>
     */
    public static function columnsFor(?Project $project, User $user): Collection
    {
        if ($project && $project->exists) {
            self::seedFor($project);

            return $project->boardColumns()->get();
        }

        return self::defaultColumnsFor($user);
    }

    /**
     * @return array<int, string>
     */
    public static function statusKeys(?Project $project, User $user): array
    {
        return self::columnsFor($project, $user)->pluck('key')->all();
    }

    public static function firstStatusKey(?Project $project, User $user): string
    {
        return self::statusKeys($project, $user)[0] ?? 'Pending';
    }

    /**
     * Resolve a column for the given scope, copying it from the user's default
     * set when the task belongs to a project that does not define it yet.
     */
    public static function ensureColumnForScope(?Project $project, User $user, string $status): ?TaskBoardColumn
    {
        $columns = self::columnsFor($project, $user);

        $column = $columns->firstWhere('key', $status);

        if ($column) {
            return $column;
        }

        if (! $project) {
            return null;
        }

        $default = self::defaultColumnsFor($user)->firstWhere('key', $status);

        if (! $default) {
            return null;
        }

        return TaskBoardColumn::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'key' => $default->key,
            'label' => $default->label,
            'color' => $default->color,
            'sort_order' => ($columns->max('sort_order') ?? -1) + 1,
            'is_done' => $default->is_done,
        ]);
    }

    public static function propagateIsDone(TaskBoardColumn $column): void
    {
        self::tasksQuery($column)->update(['is_done' => $column->is_done]);
    }

    /**
     * @return Builder<ProjectTask>
     */
    public static function tasksQuery(TaskBoardColumn $column): Builder
    {
        return ProjectTask::query()
            ->where('status', $column->key)
            ->when(
                $column->project_id,
                fn ($query) => $query->where('project_id', $column->project_id),
                fn ($query) => $query->where('user_id', $column->user_id)->whereNull('project_id'),
            );
    }

    /**
     * @param  array<int, array{key: string, label: string, color: string, is_done: bool}>  $columns
     */
    private static function insertDefaults(int $userId, ?int $projectId, array $columns): void
    {
        DB::transaction(function () use ($userId, $projectId, $columns) {
            foreach ($columns as $index => $column) {
                TaskBoardColumn::create([
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'key' => $column['key'],
                    'label' => $column['label'],
                    'color' => $column['color'],
                    'sort_order' => $index,
                    'is_done' => $column['is_done'],
                ]);
            }
        });
    }
}
