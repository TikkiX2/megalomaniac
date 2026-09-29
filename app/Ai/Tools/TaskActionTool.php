<?php

namespace App\Ai\Tools;

use App\Ai\Support\MarkdownToYoopta;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected TaskService $tasks,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create, update, complete, move or delete the user\'s tasks (standalone, personal or freelance). move_task changes column/project and can reorder a board. Use this for anything task related.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus tareas').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_task' => 'crear una tarea',
            'update_task' => 'actualizar una tarea',
            'complete_task' => 'completar una tarea',
            'move_task' => 'mover una tarea',
            'delete_task' => 'eliminar una tarea',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_task' => $this->create($request),
                'update_task' => $this->update($request),
                'complete_task' => $this->complete($request),
                'move_task' => $this->move($request),
                'delete_task' => $this->delete($request),
                default => $this->error('Invalid action. Use: create_task, update_task, complete_task, move_task, delete_task'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function create(Request $request): string
    {
        $projectId = $request['project_id'] ?? null;

        $task = $this->tasks->create($this->user, [
            'title' => $request['title'] ?? 'Task',
            'description' => $this->markdownDescription($request['description'] ?? null),
            'project_id' => $projectId === 0 ? null : $projectId,
            'status' => $request['status'] ?? null,
            'priority' => $request['priority'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'start_date' => $request['start_date'] ?? null,
            'tags' => $request['tags'] ?? null,
            'area' => $request['area'] ?? null,
            'module' => $request['module'] ?? null,
        ]);

        return $this->success('Task created', ['task' => $task->toArray()]);
    }

    private function update(Request $request): string
    {
        $task = $this->findTask($request['task_id'] ?? null);

        if (! $task) {
            return $this->error('Task not found');
        }

        $data = array_filter([
            'title' => $request['title'] ?? null,
            'priority' => $request['priority'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'start_date' => $request['start_date'] ?? null,
            'tags' => $request['tags'] ?? null,
            'area' => $request['area'] ?? null,
            'module' => $request['module'] ?? null,
            'status' => $request['status'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        if ($request->offsetExists('description')) {
            $data['description'] = $this->markdownDescription($request['description']);
        }

        if ($request->offsetExists('is_archived')) {
            $data['is_archived'] = (bool) $request['is_archived'];
        }

        if ($request->offsetExists('project_id')) {
            $projectId = $request['project_id'];
            $data['project_id'] = $projectId === 0 ? null : $projectId;
        }

        $task = $this->tasks->update($this->user, $task, $data);

        return $this->success('Task updated', [
            'task' => $task->only(['id', 'title', 'status', 'priority', 'due_date', 'is_done', 'project_id', 'is_archived']),
        ]);
    }

    private function complete(Request $request): string
    {
        $task = $this->findTask($request['task_id'] ?? null);

        if (! $task) {
            return $this->error('Task not found');
        }

        $task = $this->tasks->complete($this->user, $task);

        return $this->success('Task completed', [
            'task' => $task->only(['id', 'title', 'status', 'is_done']),
        ]);
    }

    private function move(Request $request): string
    {
        $task = $this->findTask($request['task_id'] ?? null);

        if (! $task) {
            return $this->error('Task not found');
        }

        $payload = [
            'status' => $request['status'] ?? null,
            'ordered_ids' => $request['ordered_ids'] ?? null,
        ];

        if ($request->offsetExists('project_id')) {
            $projectId = $request['project_id'];
            $payload['project_id'] = $projectId === 0 ? null : $projectId;
        }

        $task = $this->tasks->move($this->user, $task, array_filter(
            $payload,
            fn (mixed $value): bool => $value !== null,
        ));

        return $this->success('Task moved', [
            'task' => $task->only(['id', 'title', 'status', 'is_done', 'project_id', 'sort_order']),
        ]);
    }

    private function delete(Request $request): string
    {
        $task = $this->findTask($request['task_id'] ?? null);

        if (! $task) {
            return $this->error('Task not found');
        }

        $this->tasks->delete($this->user, $task);

        return $this->success('Task deleted', ['task' => ['id' => $task->id, 'title' => $task->title]]);
    }

    private function findTask(mixed $taskId): ?ProjectTask
    {
        if (! $taskId) {
            return null;
        }

        return ProjectTask::where('user_id', $this->user->id)->find((int) $taskId);
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function markdownDescription(mixed $description): ?array
    {
        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        $blocks = MarkdownToYoopta::convert($description);

        return $blocks === [] ? null : $blocks;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_task', 'update_task', 'complete_task', 'move_task', 'delete_task'])
                ->description('Action to perform')
                ->required(),
            'task_id' => $schema->integer()->description('Task ID (update_task, complete_task, move_task, delete_task)'),
            'project_id' => $schema->integer()->description('Project ID (create_task, update_task, move_task; use 0 to detach the task)'),
            'title' => $schema->string()->description('Title (create_task, update_task)'),
            'description' => $schema->string()->description('Description (create_task, update_task); Markdown is welcome (## headings, - lists, **bold**)'),
            'status' => $schema->string()->description('Board column key (create_task, update_task, move_task; must match a board column)'),
            'priority' => $schema->string()->description('Priority (create_task, update_task)'),
            'due_date' => $schema->string()->description('Due date YYYY-MM-DD (create_task, update_task)'),
            'start_date' => $schema->string()->description('Start date YYYY-MM-DD (create_task, update_task)'),
            'tags' => $schema->array()->description('Tags (create_task, update_task)')->items($schema->string()),
            'area' => $schema->string()->description('Area label (create_task, update_task)'),
            'module' => $schema->string()->description('Free-form module label (create_task, update_task)'),
            'is_archived' => $schema->boolean()->description('Archive flag (update_task)'),
            'ordered_ids' => $schema->array()->description('Task IDs in the new order (move_task)')->items($schema->integer()),
        ];
    }
}
