<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\ProjectTask;
use App\Services\Tasks\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PersonalTaskWriteTool extends Tool
{
    protected string $name = 'personal-task-write';

    protected string $description = 'Create, update, move or delete personal tasks (standalone or inside personal projects). Moving to a freelance project is rejected: use the freelance tools for those.';

    public function __construct(protected TaskService $tasks) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create, update, move, delete')->enum(['create', 'update', 'move', 'delete'])->required(),
            'task_id' => $schema->integer()->description('Task ID (required for update, move and delete)'),
            'title' => $schema->string()->description('Task title (required for create)')->max(255),
            'description' => $schema->string()->description('Description'),
            'project_id' => $schema->integer()->description('Personal project ID (create, update, move; use 0 to detach the task from its project)'),
            'status' => $schema->string()->description('Board column key (create, update, move; must match the board)'),
            'priority' => $schema->string()->description('Priority: low, medium, high'),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format'),
            'start_date' => $schema->string()->description('Start date in YYYY-MM-DD format'),
            'is_archived' => $schema->boolean()->description('Archive flag (update)'),
            'sort_order' => $schema->integer()->description('Sort order (move)'),
            'ordered_ids' => $schema->array()->description('Task IDs in the new order (move)')->items($schema->integer()),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action', '');
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create' => $this->create($request, $user),
                'update' => $this->update($request, $user),
                'move' => $this->move($request, $user),
                'delete' => $this->delete($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function create(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $task = $this->tasks->create($user, [
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'project_id' => $request->get('project_id') ?: null,
            'status' => $request->get('status'),
            'priority' => $request->get('priority'),
            'due_date' => $request->get('due_date'),
            'start_date' => $request->get('start_date'),
        ], 'personal');

        return Response::structured([
            'task' => $task->fresh(),
            'message' => 'Personal task created successfully.',
        ]);
    }

    private function update(Request $request, $user): Response|ResponseFactory
    {
        $task = $this->findTask($request, $user);

        if (! $task instanceof ProjectTask) {
            return $task;
        }

        $data = array_filter([
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'status' => $request->get('status'),
            'priority' => $request->get('priority'),
            'due_date' => $request->get('due_date'),
            'start_date' => $request->get('start_date'),
        ], fn ($value) => $value !== null);

        if ($request->get('is_archived') !== null) {
            $data['is_archived'] = (bool) $request->get('is_archived');
        }

        if ($request->get('project_id') !== null) {
            $data['project_id'] = $request->get('project_id') === 0 ? null : $request->get('project_id');
        }

        $task = $this->tasks->update($user, $task, $data, 'personal');

        return Response::structured([
            'task' => $task,
            'message' => 'Personal task updated successfully.',
        ]);
    }

    private function move(Request $request, $user): Response|ResponseFactory
    {
        $task = $this->findTask($request, $user);

        if (! $task instanceof ProjectTask) {
            return $task;
        }

        $payload = [
            'status' => $request->get('status'),
            'sort_order' => $request->get('sort_order'),
            'ordered_ids' => $request->get('ordered_ids'),
        ];

        if ($request->get('project_id') !== null) {
            $payload['project_id'] = $request->get('project_id') === 0 ? null : $request->get('project_id');
        }

        $task = $this->tasks->move($user, $task, array_filter(
            $payload,
            fn ($value) => $value !== null,
        ), 'personal');

        return Response::structured([
            'task' => $task,
            'message' => 'Personal task moved successfully.',
        ]);
    }

    private function delete(Request $request, $user): Response|ResponseFactory
    {
        $task = $this->findTask($request, $user);

        if (! $task instanceof ProjectTask) {
            return $task;
        }

        $this->tasks->delete($user, $task);

        return Response::structured([
            'message' => 'Personal task deleted successfully.',
        ]);
    }

    private function findTask(Request $request, $user): ProjectTask|Response|ResponseFactory
    {
        $task = ProjectTask::where('user_id', $user->id)->find($request->get('task_id', 0));

        if (! $task) {
            return Response::error('Task not found or unauthorized.');
        }

        return $task;
    }
}
