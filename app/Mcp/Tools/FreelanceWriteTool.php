<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quote;
use App\Services\Freelance\FreelanceService;
use App\Services\Projects\ProjectService;
use App\Services\TaskBoardColumnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class FreelanceWriteTool extends Tool
{
    protected string $name = 'freelance-write';

    protected string $description = 'Create and update clients, projects, and tasks for the authenticated user. Updating a project type moves it between Freelance and Personal and remaps its task board.';

    public function __construct(
        protected ProjectService $projects,
        protected FreelanceService $freelance,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_client, update_client, delete_client, create_project, update_project, archive_project, delete_project, create_quote, update_quote, delete_quote, convert_quote, create_task')->enum(['create_client', 'update_client', 'delete_client', 'create_project', 'update_project', 'archive_project', 'delete_project', 'create_quote', 'update_quote', 'delete_quote', 'convert_quote', 'create_task'])->required(),
            'name' => $schema->string()->description('Name (required for client and project creation; optional for project update)')->max(255),
            'email' => $schema->string()->description('Email (for create_client, update_client)')->max(255),
            'address' => $schema->string()->description('Address (update_client)')->max(255),
            'tax_id' => $schema->string()->description('Tax ID (update_client)')->max(50),
            'phone' => $schema->string()->description('Phone (for create_client)')->max(50),
            'company' => $schema->string()->description('Company name (for create_client)')->max(255),
            'client_id' => $schema->integer()->description('Client ID (required for create_project; required to move a project to freelance)'),
            'project_id' => $schema->integer()->description('Project ID (create_task, update_project, archive_project, delete_project)'),
            'description' => $schema->string()->description('Description (for create_project, update_project and create_task)'),
            'status' => $schema->string()->description('Status (for create_project, update_project and create_task)')->max(50),
            'type' => $schema->string()->description('Project type: personal or freelance; changing it moves the project between modules (create_project, update_project)')->enum(['personal', 'freelance']),
            'total_amount' => $schema->number()->description('Total amount (for create_project, update_project)')->min(0),
            'currency_id' => $schema->integer()->description('Currency ID (for create_project, update_project)'),
            'deadline' => $schema->string()->description('Deadline in YYYY-MM-DD format (update_project)'),
            'start_date' => $schema->string()->description('Start date in YYYY-MM-DD format (update_project)'),
            'end_date' => $schema->string()->description('End date in YYYY-MM-DD format (update_project)'),
            'area' => $schema->string()->description('Area label (update_project)')->max(100),
            'module' => $schema->string()->description('Free-form module label (update_project)')->max(100),
            'priority' => $schema->string()->description('Priority: low, medium, high (create_task); project priority label (update_project)'),
            'tags' => $schema->array()->description('Tags (update_project)')->items($schema->string()),
            'notes' => $schema->string()->description('Notes (update_project)'),
            'is_archived' => $schema->boolean()->description('Archive flag (update_project)'),
            'quote_id' => $schema->integer()->description('Quote ID (update_quote, delete_quote, convert_quote)'),
            'issue_date' => $schema->string()->description('Issue date YYYY-MM-DD (create_quote, update_quote)'),
            'hourly_rate' => $schema->number()->description('Hourly rate (create_quote)'),
            'items' => $schema->array()->description('Quote items (create_quote, update_quote replaces them)')->items($schema->object([
                'description' => $schema->string(),
                'hours' => $schema->number(),
                'hourly_rate' => $schema->number(),
                'subtotal' => $schema->number(),
            ])),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format (for create_task)'),
            'responsible' => $schema->string()->description('Responsible person (for create_task)')->max(255),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create_client' => $this->createClient($request, $user),
                'update_client' => $this->updateClient($request, $user),
                'delete_client' => $this->deleteClient($request, $user),
                'create_project' => $this->createProject($request, $user),
                'update_project' => $this->updateProject($request, $user),
                'archive_project' => $this->archiveProject($request, $user),
                'delete_project' => $this->deleteProject($request, $user),
                'create_quote' => $this->createQuote($request, $user),
                'update_quote' => $this->updateQuote($request, $user),
                'delete_quote' => $this->deleteQuote($request, $user),
                'convert_quote' => $this->convertQuote($request, $user),
                'create_task' => $this->createTask($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
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
            'client_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'in:personal,freelance'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer'],
        ]);

        $project = $this->projects->create($user, [
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

    private function updateProject(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request->get('project_id', 0))
            ->where('user_id', $user->id)
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $data = array_filter([
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'client_id' => $request->get('client_id'),
            'description' => $request->get('description'),
            'status' => $request->get('status'),
            'total_amount' => $request->get('total_amount'),
            'currency_id' => $request->get('currency_id'),
            'deadline' => $request->get('deadline'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'area' => $request->get('area'),
            'module' => $request->get('module'),
            'priority' => $request->get('priority'),
            'tags' => $request->get('tags'),
            'notes' => $request->get('notes'),
        ], fn ($value) => $value !== null);

        if ($request->get('is_archived') !== null) {
            $data['is_archived'] = (bool) $request->get('is_archived');
        }

        $project = $this->projects->update($user, $project, $data);

        return Response::structured([
            'project' => $project->fresh()->load('client'),
            'message' => 'Project updated successfully.',
        ]);
    }

    private function archiveProject(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request->get('project_id', 0))
            ->where('user_id', $user->id)
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $project = $this->projects->archive($user, $project);

        return Response::structured([
            'project' => $project->only(['id', 'name', 'type', 'is_archived']),
            'message' => 'Project archived successfully.',
        ]);
    }

    private function deleteProject(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request->get('project_id', 0))
            ->where('user_id', $user->id)
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $this->projects->delete($user, $project);

        return Response::structured([
            'message' => 'Project deleted successfully.',
        ]);
    }

    private function updateClient(Request $request, $user): Response|ResponseFactory
    {
        $client = Client::where('user_id', $user->id)->find($request->get('client_id', 0));

        if (! $client) {
            return Response::error('Client not found or unauthorized.');
        }

        $client = $this->freelance->updateClient($user, $client, array_filter([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'company' => $request->get('company'),
            'address' => $request->get('address'),
            'tax_id' => $request->get('tax_id'),
            'notes' => $request->get('notes'),
        ], fn ($value) => $value !== null));

        return Response::structured([
            'client' => $client,
            'message' => 'Client updated successfully.',
        ]);
    }

    private function deleteClient(Request $request, $user): Response|ResponseFactory
    {
        $client = Client::where('user_id', $user->id)->find($request->get('client_id', 0));

        if (! $client) {
            return Response::error('Client not found or unauthorized.');
        }

        $this->freelance->deleteClient($user, $client);

        return Response::structured(['message' => 'Client deleted successfully.']);
    }

    private function createQuote(Request $request, $user): Response|ResponseFactory
    {
        $quote = $this->freelance->createQuote($user, [
            'client_id' => $request->get('client_id'),
            'project_id' => $request->get('project_id'),
            'title' => $request->get('title'),
            'issue_date' => $request->get('issue_date', now()->toDateString()),
            'currency_id' => $request->get('currency_id'),
            'hourly_rate' => $request->get('hourly_rate'),
            'notes' => $request->get('notes'),
            'items' => $request->get('items', []),
        ]);

        return Response::structured([
            'quote' => $quote,
            'message' => 'Quote created successfully.',
        ]);
    }

    private function updateQuote(Request $request, $user): Response|ResponseFactory
    {
        $quote = Quote::where('user_id', $user->id)->find($request->get('quote_id', 0));

        if (! $quote) {
            return Response::error('Quote not found or unauthorized.');
        }

        $data = array_filter([
            'client_id' => $request->get('client_id'),
            'title' => $request->get('title'),
            'status' => $request->get('status'),
            'issue_date' => $request->get('issue_date'),
            'notes' => $request->get('notes'),
        ], fn ($value) => $value !== null);

        $items = $request->get('items');

        if ($items !== null) {
            $data['items'] = $items;
        }

        $quote = $this->freelance->updateQuote($user, $quote, $data);

        return Response::structured([
            'quote' => $quote,
            'message' => 'Quote updated successfully.',
        ]);
    }

    private function deleteQuote(Request $request, $user): Response|ResponseFactory
    {
        $quote = Quote::where('user_id', $user->id)->find($request->get('quote_id', 0));

        if (! $quote) {
            return Response::error('Quote not found or unauthorized.');
        }

        $this->freelance->deleteQuote($user, $quote);

        return Response::structured(['message' => 'Quote deleted successfully.']);
    }

    private function convertQuote(Request $request, $user): Response|ResponseFactory
    {
        $quote = Quote::where('user_id', $user->id)->find($request->get('quote_id', 0));

        if (! $quote) {
            return Response::error('Quote not found or unauthorized.');
        }

        $project = $this->freelance->convertQuoteToProject($user, $quote);

        return Response::structured([
            'project' => $project->load('client'),
            'quote' => $quote->fresh()->only(['id', 'status', 'project_id']),
            'message' => 'Quote converted to project successfully.',
        ]);
    }

    private function createTask(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'responsible' => ['nullable', 'string', 'max:255'],
        ]);

        $projectId = $request->get('project_id');
        $project = null;

        if ($projectId) {
            $project = Project::where('id', $projectId)
                ->where('user_id', $user->id)
                ->first();

            if (! $project) {
                return Response::error('Project not found or unauthorized.');
            }

            if ($project->type !== 'freelance') {
                return Response::error('The project does not belong to the freelance module.');
            }

            $order = $project->tasks()->max('sort_order') ?? 0;
        } else {
            $order = ProjectTask::where('user_id', $user->id)->whereNull('project_id')->max('sort_order') ?? 0;
        }

        $statusKey = $request->get('status') ?: TaskBoardColumnService::firstStatusKey($project, $user);
        $column = TaskBoardColumnService::columnsFor($project, $user)->firstWhere('key', $statusKey);

        $task = ProjectTask::create([
            'project_id' => $projectId,
            'user_id' => $user->id,
            'title' => $request->get('name'),
            'description' => $request->get('description'),
            'status' => $column?->key ?? $statusKey,
            'is_done' => (bool) $column?->is_done,
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
