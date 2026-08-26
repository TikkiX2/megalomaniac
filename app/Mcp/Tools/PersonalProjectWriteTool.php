<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PersonalProjectWriteTool extends Tool
{
    protected string $name = 'personal-project-write';

    protected string $description = 'Create, update, or delete personal projects for the authenticated user.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create, update, delete')->enum(['create', 'update', 'delete'])->required(),
            'project_id' => $schema->integer()->description('Project ID (required for update and delete)'),
            'name' => $schema->string()->description('Project name (required for create)')->max(255),
            'description' => $schema->string()->description('Description (for create and update)'),
            'status' => $schema->string()->description('Status: pending, in_progress, completed, archived')->enum(['pending', 'in_progress', 'completed', 'archived']),
            'deadline' => $schema->string()->description('Deadline in YYYY-MM-DD format'),
            'priority' => $schema->string()->description('Priority: low, medium, high'),
            'notes' => $schema->string()->description('Notes'),
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,archived'],
            'deadline' => ['nullable', 'date'],
            'priority' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'name' => $request['name'],
            'description' => $request['description'] ?? null,
            'type' => 'personal',
            'status' => $request['status'] ?? 'pending',
            'deadline' => $request['deadline'] ?? null,
            'priority' => $request['priority'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]);

        return Response::structured([
            'project' => $project->fresh(),
            'message' => 'Personal project created successfully.',
        ]);
    }

    private function update(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request['project_id'] ?? 0)
            ->where('user_id', $user->id)
            ->where('type', 'personal')
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $data = array_filter([
            'name' => $request['name'] ?? null,
            'description' => $request['description'] ?? null,
            'status' => $request['status'] ?? null,
            'deadline' => $request['deadline'] ?? null,
            'priority' => $request['priority'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn ($v) => $v !== null);

        $project->update($data);

        return Response::structured([
            'project' => $project->fresh(),
            'message' => 'Personal project updated successfully.',
        ]);
    }

    private function delete(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request['project_id'] ?? 0)
            ->where('user_id', $user->id)
            ->where('type', 'personal')
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $project->delete();

        return Response::structured([
            'message' => 'Personal project deleted successfully.',
        ]);
    }
}
