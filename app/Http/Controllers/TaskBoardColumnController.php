<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskBoardColumn\StoreTaskBoardColumnRequest;
use App\Http\Requests\TaskBoardColumn\UpdateTaskBoardColumnRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskBoardColumn;
use App\Services\TaskBoardColumnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskBoardColumnController extends Controller
{
    public function store(StoreTaskBoardColumnRequest $request): JsonResponse
    {
        $user = $request->user();
        $projectId = $request->validated('project_id');

        if ($projectId) {
            $project = Project::where('id', $projectId)->where('user_id', $user->id)->firstOrFail();
            $columns = TaskBoardColumnService::columnsFor($project, $user);
        } else {
            $columns = TaskBoardColumnService::defaultColumnsFor($user);
        }

        $column = TaskBoardColumn::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'key' => $this->uniqueKey($request->validated('label'), $user->id, $projectId),
            'label' => $request->validated('label'),
            'color' => $request->validated('color', 'slate'),
            'sort_order' => ($columns->max('sort_order') ?? -1) + 1,
            'is_done' => $request->boolean('is_done'),
        ]);

        return response()->json(['column' => $column]);
    }

    public function update(UpdateTaskBoardColumnRequest $request, TaskBoardColumn $column): JsonResponse
    {
        $this->authorizeColumn($request, $column);

        $wasDone = $column->is_done;

        $column->update($request->safe()->only(['label', 'color', 'is_done', 'sort_order']));

        if ($column->is_done !== $wasDone) {
            TaskBoardColumnService::propagateIsDone($column);
        }

        // Default-set columns act as a template: keep project copies in sync.
        if (! $column->project_id) {
            TaskBoardColumn::where('user_id', $column->user_id)
                ->where('key', $column->key)
                ->whereNotNull('project_id')
                ->get()
                ->each(function (TaskBoardColumn $sibling) use ($column, $wasDone) {
                    $sibling->update([
                        'label' => $column->label,
                        'color' => $column->color,
                        'is_done' => $column->is_done,
                    ]);

                    if ($sibling->is_done !== $wasDone) {
                        TaskBoardColumnService::propagateIsDone($sibling);
                    }
                });
        }

        return response()->json(['column' => $column->fresh()]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $columns = TaskBoardColumn::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ordered_ids'])
            ->get()
            ->keyBy('id');

        foreach ($validated['ordered_ids'] as $index => $id) {
            $columns->get($id)?->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, TaskBoardColumn $column): JsonResponse
    {
        $this->authorizeColumn($request, $column);

        $siblings = $column->project_id
            ? collect()
            : TaskBoardColumn::where('user_id', $column->user_id)
                ->where('key', $column->key)
                ->whereNotNull('project_id')
                ->get();

        $projectIds = $siblings->pluck('project_id');

        $taskQuery = ProjectTask::query()
            ->where('status', $column->key)
            ->when(
                $column->project_id,
                fn ($query) => $query->where('project_id', $column->project_id),
                fn ($query) => $query->where('user_id', $column->user_id)
                    ->where(fn ($sub) => $sub->whereNull('project_id')->orWhereIn('project_id', $projectIds)),
            );

        if ($taskQuery->count() > 0) {
            $validated = $request->validate([
                'move_to' => ['required', 'string', 'max:50'],
            ]);

            $destination = TaskBoardColumn::where('user_id', $column->user_id)
                ->where('key', $validated['move_to'])
                ->when(
                    $column->project_id,
                    fn ($query) => $query->where('project_id', $column->project_id),
                    fn ($query) => $query->whereNull('project_id'),
                )
                ->whereKeyNot($column->id)
                ->first();

            if (! $destination) {
                return response()->json([
                    'message' => 'La columna destino no existe en este tablero.',
                ], 422);
            }

            foreach ($projectIds as $projectId) {
                $project = Project::find($projectId);

                if ($project) {
                    TaskBoardColumnService::ensureColumnForScope($project, $request->user(), $destination->key);
                }
            }

            $taskQuery->update([
                'status' => $destination->key,
                'is_done' => $destination->is_done,
            ]);
        }

        $siblings->each->delete();
        $column->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeColumn(Request $request, TaskBoardColumn $column): void
    {
        abort_if($column->user_id !== $request->user()->id, 403);

        if ($column->project_id && $column->project?->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function uniqueKey(string $label, int $userId, ?int $projectId): string
    {
        $base = Str::slug($label, '_') ?: 'column';

        $exists = fn (string $key) => TaskBoardColumn::where('user_id', $userId)
            ->when(
                $projectId,
                fn ($query) => $query->where('project_id', $projectId),
                fn ($query) => $query->whereNull('project_id'),
            )
            ->where('key', $key)
            ->exists();

        $key = $base;
        $suffix = 2;

        while ($exists($key)) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
