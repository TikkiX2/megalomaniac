<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\ProjectTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PersonalTaskWriteTool extends Tool
{
    protected string $name = 'personal-task-write';

    protected string $description = 'Create, update, or delete personal tasks (standalone tasks without a project).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create, update, delete')->enum(['create', 'update', 'delete'])->required(),
            'task_id' => $schema->integer()->description('Task ID (required for update and delete)'),
            'title' => $schema->string()->description('Task title (required for create)')->max(255),
            'description' => $schema->string()->description('Description (for create and update)'),
            'status' => $schema->string()->description('Status: Pending, In Progress, Done, Archived'),
            'priority' => $schema->string()->description('Priority: low, medium, high')->enum(['low', 'medium', 'high']),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = $request['action'] ?? '';
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        return match ($action) {
            'create' => $this->create($request, $user),
            'update' => $this->update($request, $user),
            'delete' => $this->delete($request, $user),
            default => Response::error("Invalid action: {$action}"),
        };
    }

    private function create(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = ProjectTask::create([
            'user_id' => $user->id,
            'project_id' => null,
            'title' => $request['title'],
            'description' => $request['description'] ?? null,
            'status' => $request['status'] ?? 'Pending',
            'priority' => $request['priority'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'sort_order' => ProjectTask::where('user_id', $user->id)->whereNull('project_id')->max('sort_order') + 1,
        ]);

        return Response::structured([
            'task' => $task->fresh(),
            'message' => 'Personal task created successfully.',
        ]);
    }

    private function update(Request $request, $user): Response|ResponseFactory
    {
        $task = ProjectTask::where('id', $request['task_id'] ?? 0)
            ->where('user_id', $user->id)
            ->whereNull('project_id')
            ->first();

        if (! $task) {
            return Response::error('Task not found or unauthorized.');
        }

        $data = array_filter([
            'title' => $request['title'] ?? null,
            'description' => $request['description'] ?? null,
            'status' => $request['status'] ?? null,
            'priority' => $request['priority'] ?? null,
            'due_date' => $request['due_date'] ?? null,
        ], fn ($v) => $v !== null);

        $task->update($data);

        return Response::structured([
            'task' => $task->fresh(),
            'message' => 'Personal task updated successfully.',
        ]);
    }

    private function delete(Request $request, $user): Response|ResponseFactory
    {
        $task = ProjectTask::where('id', $request['task_id'] ?? 0)
            ->where('user_id', $user->id)
            ->whereNull('project_id')
            ->first();

        if (! $task) {
            return Response::error('Task not found or unauthorized.');
        }

        $task->delete();

        return Response::structured([
            'message' => 'Personal task deleted successfully.',
        ]);
    }
}
