<?php

namespace App\Services\Projects;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\TaskBoardColumnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectTypeService
{
    /**
     * Move a project between the personal and freelance modules keeping its
     * board consistent: destination defaults are seeded, task statuses are
     * remapped and the previous type's default columns are removed.
     */
    public function changeType(User $user, Project $project, string $type, ?int $clientId = null): Project
    {
        if (! in_array($type, ['personal', 'freelance'], true)) {
            throw new InvalidArgumentException('Invalid project type; use personal or freelance.');
        }

        if ($project->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this project.');
        }

        return DB::transaction(function () use ($user, $project, $type, $clientId): Project {
            if ($type === 'freelance') {
                if ($clientId === null) {
                    throw new InvalidArgumentException('Freelance projects require a client.');
                }

                $client = Client::where('user_id', $user->id)->find($clientId);

                if (! $client) {
                    throw new ModelNotFoundException('Client not found.');
                }

                $project->client_id = $client->id;
            } elseif ($this->isAutoPersonalClient($project->client)) {
                $project->client_id = null;
            }

            $project->type = $type;
            $project->save();

            $staleKeys = $this->staleDefaultKeys($type);

            TaskBoardColumnService::seedDefaultsForType($project);

            $this->remapTasks($project, $staleKeys, $type);
            $this->removeStaleDefaultColumns($project, $staleKeys);

            return $project->fresh(['boardColumns', 'tasks']);
        });
    }

    /**
     * Default column keys of the previous type that do not exist in the
     * destination type; they are remapped and removed on a type change.
     *
     * @return array<int, string>
     */
    private function staleDefaultKeys(string $type): array
    {
        $targetKeys = array_column(TaskBoardColumnService::defaultsForType($type), 'key');
        $otherKeys = array_column(
            TaskBoardColumnService::defaultsForType($type === 'personal' ? 'freelance' : 'personal'),
            'key',
        );

        return array_values(array_diff($otherKeys, $targetKeys));
    }

    /**
     * @param  array<int, string>  $staleKeys
     */
    private function remapTasks(Project $project, array $staleKeys, string $type): void
    {
        $defaults = TaskBoardColumnService::defaultsForType($type);
        $columns = $project->boardColumns()->whereNotIn('key', $staleKeys)->get();

        $doneKey = collect($defaults)->firstWhere('is_done', true)['key']
            ?? $columns->firstWhere('is_done', true)?->key;
        $firstKey = collect($defaults)->firstWhere('is_done', false)['key']
            ?? $columns->first()?->key;

        $project->tasks()->get()->each(function (ProjectTask $task) use ($columns, $doneKey, $firstKey): void {
            if ($task->is_done && $doneKey !== null) {
                $status = $doneKey;
            } elseif ($columns->contains(fn ($column): bool => $column->key === $task->status)) {
                $status = $task->status;
            } else {
                $status = $firstKey;
            }

            if ($status === null) {
                return;
            }

            $task->update([
                'status' => $status,
                'is_done' => $status === $doneKey,
            ]);
        });
    }

    /**
     * @param  array<int, string>  $staleKeys
     */
    private function removeStaleDefaultColumns(Project $project, array $staleKeys): void
    {
        if ($staleKeys === []) {
            return;
        }

        $project->boardColumns()
            ->whereIn('key', $staleKeys)
            ->get()
            ->each(fn ($column) => $column->delete());
    }

    private function isAutoPersonalClient(?Client $client): bool
    {
        return $client !== null
            && $client->name === 'Personal'
            && ! $client->email
            && ! $client->phone;
    }
}
