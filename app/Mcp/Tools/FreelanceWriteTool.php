<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class FreelanceWriteTool extends Tool
{
    protected string $name = 'freelance-write';

    protected string $description = 'Create clients, projects, and tasks for the authenticated user.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_client, create_project, create_task')->enum(['create_client', 'create_project', 'create_task'])->required(),
            'name' => $schema->string()->description('Name (required for all actions)')->maxLength(255),
            'email' => $schema->string()->description('Email (for create_client)')->maxLength(255),
            'phone' => $schema->string()->description('Phone (for create_client)')->maxLength(50),
            'company' => $schema->string()->description('Company name (for create_client)')->maxLength(255),
            'client_id' => $schema->integer()->description('Client ID (required for create_project)'),
            'project_id' => $schema->integer()->description('Project ID (required for create_task)'),
            'description' => $schema->string()->description('Description (for create_project and create_task)'),
            'status' => $schema->string()->description('Status (for create_project and create_task)')->maxLength(50),
            'type' => $schema->string()->description('Project type: personal or freelance (for create_project)')->enum(['personal', 'freelance']),
            'total_amount' => $schema->number()->description('Total amount (for create_project)')->minimum(0),
            'currency_id' => $schema->integer()->description('Currency ID (for create_project)'),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format (for create_task)'),
            'priority' => $schema->string()->description('Priority: low, medium, high (for create_task)')->enum(['low', 'medium', 'high']),
            'responsible' => $schema->string()->description('Responsible person (for create_task)')->maxLength(255),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        return match ($action) {
            'create_client' => $this->createClient($request, $user),
            'create_project' => $this->createProject($request, $user),
            'create_task' => $this->createTask($request, $user),
            default => Response::error("Invalid action: {$action}"),
        };
    }

    private function createClient(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
        ]);

        $client = Client::create([
            'user_id' => $user->id,
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'company' => $request->get('company'),
            'is_active' => true,
        ]);

        return Response::structured([
            'client' => $client->fresh(),
            'message' => 'Client created successfully.',
        ]);
    }

    private function createProject(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'in:personal,freelance'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'client_id' => $request->get('client_id'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'status' => $request->get('status', 'pending'),
            'type' => $request->get('type', 'freelance'),
            'total_amount' => $request->get('total_amount'),
            'currency_id' => $request->get('currency_id'),
        ]);

        return Response::structured([
            'project' => $project->fresh()->load('client'),
            'message' => 'Project created successfully.',
        ]);
    }

    private function createTask(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'responsible' => ['nullable', 'string', 'max:255'],
        ]);

        $project = Project::where('id', $request->get('project_id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $order = $project->tasks()->max('sort_order') ?? 0;

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => $request->get('name'),
            'description' => $request->get('description'),
            'status' => $request->get('status', 'Pending'),
            'due_date' => $request->get('due_date'),
            'priority' => $request->get('priority'),
            'responsible' => $request->get('responsible'),
            'sort_order' => $order + 1,
        ]);

        return Response::structured([
            'task' => $task->fresh()->load('project'),
            'message' => 'Task created successfully.',
        ]);
    }
}
