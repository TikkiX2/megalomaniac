<?php

namespace App\Services\Tasks;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskBoardColumn;
use App\Models\User;
use App\Services\TaskBoardColumnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, ?string $projectType = null): ProjectTask
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('The title field is required.');
        }

        $project = $this->resolveProject($user, $data['project_id'] ?? null, $projectType);
        $column = $this->resolveColumn($project, $user, $data['status'] ?? null);

        return ProjectTask::create([
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'status' => $column->key,
            'is_done' => $column->is_done,
            'priority' => $data['priority'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'tags' => $data['tags'] ?? null,
            'area' => $data['area'] ?? null,
            'module' => $data['module'] ?? null,
            'is_archived' => (bool) ($data['is_archived'] ?? false),
            'sort_order' => $this->nextSortOrder($user, $project),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, ProjectTask $task, array $data, ?string $projectType = null): ProjectTask
    {
        $this->assertOwner($user, $task);

        $movesProject = array_key_exists('project_id', $data);
        $targetProject = $movesProject
            ? $this->resolveProject($user, $data['project_id'], $projectType)
            : $task->project;

        if ($movesProject) {
            $data['sort_order'] = $this->nextSortOrder($user, $targetProject);
        }

        if (array_key_exists('status', $data) || $movesProject) {
            $column = $this->resolveColumn(
                $targetProject,
                $user,
                $data['status'] ?? $task->status,
                $movesProject && $targetProject?->id !== $task->project_id,
            );

            $data['status'] = $column->key;
            $data['is_done'] = $column->is_done;
        }

        $task->update($data);

        return $task->fresh();
    }

    public function complete(User $user, ProjectTask $task): ProjectTask
    {
        $this->assertOwner($user, $task);

        $doneKey = TaskBoardColumnService::columnsFor($task->project, $user)
            ->firstWhere('is_done', true)?->key ?? 'Done';

        $task->update(['status' => $doneKey, 'is_done' => true]);

        return $task->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function move(User $user, ProjectTask $task, array $data, ?string $projectType = null): ProjectTask
    {
        $this->assertOwner($user, $task);

        $status = (string) ($data['status'] ?? '');

        if ($status === '') {
            throw new InvalidArgumentException('The status field is required.');
        }

        $movesProject = array_key_exists('project_id', $data) && $data['project_id'] !== null;
        $targetProject = $movesProject
            ? $this->resolveProject($user, $data['project_id'], $projectType)
            : $task->project;

        $column = $this->resolveColumn(
            $targetProject,
            $user,
            $status,
            $movesProject && $targetProject?->id !== $task->project_id,
        );
        $orderedIds = (array) ($data['ordered_ids'] ?? []);

        DB::transaction(function () use ($task, $targetProject, $movesProject, $column, $data, $orderedIds): void {
            $task->update(array_filter([
                'project_id' => $movesProject ? $targetProject?->id : null,
                'status' => $column->key,
                'is_done' => $column->is_done,
                'sort_order' => $data['sort_order'] ?? null,
            ], fn ($value, $key) => $key === 'project_id' || $value !== null, ARRAY_FILTER_USE_BOTH));

            foreach ($orderedIds as $index => $id) {
                ProjectTask::where('user_id', $task->user_id)
                    ->whereKey($id)
                    ->update(['sort_order' => $index]);
            }
        });

        return $task->fresh();
    }

    public function delete(User $user, ProjectTask $task): void
    {
        $this->assertOwner($user, $task);

        $task->delete();
    }

    private function resolveProject(User $user, mixed $projectId, ?string $projectType): ?Project
    {
        if (! $projectId) {
            return null;
        }

        $project = Project::find($projectId);

        if (! $project) {
            throw new InvalidArgumentException('Project not found.');
        }

        if ($project->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this project.');
        }

        if ($projectType !== null && $project->type !== $projectType) {
            throw new InvalidArgumentException("The project does not belong to the {$projectType} module.");
        }

        return $project;
    }

    private function resolveColumn(?Project $project, User $user, mixed $status, bool $remapMissing = false): TaskBoardColumn
    {
        $statusKey = $status ?: TaskBoardColumnService::firstStatusKey($project, $user);

        if ($remapMissing) {
            $columns = TaskBoardColumnService::columnsFor($project, $user);
            $column = $columns->firstWhere('key', $statusKey)
                ?? $columns->firstWhere('is_done', false)
                ?? $columns->first();

            if ($column) {
                return $column;
            }
        }

        $column = TaskBoardColumnService::ensureColumnForScope($project, $user, $statusKey);

        if (! $column) {
            throw new InvalidArgumentException('The selected board column does not exist.');
        }

        return $column;
    }

    private function nextSortOrder(User $user, ?Project $project): int
    {
        return (int) ProjectTask::query()
            ->where('user_id', $user->id)
            ->when(
                $project,
                fn ($query) => $query->where('project_id', $project->id),
                fn ($query) => $query->whereNull('project_id'),
            )
            ->max('sort_order') + 1;
    }

    private function assertOwner(User $user, ProjectTask $task): void
    {
        if ($task->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this task.');
        }
    }
}
