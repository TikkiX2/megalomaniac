<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Support\MarkdownToYoopta;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PersonalProjectWriteTool extends Tool
{
    protected string $name = 'personal-project-write';

    protected string $description = 'Create, update, move to another module, or delete personal projects for the authenticated user. Changing type moves the project between Personal and Freelance and remaps its task board.';

    public function __construct(protected ProjectService $projects) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create, update, delete')->enum(['create', 'update', 'delete'])->required(),
            'project_id' => $schema->integer()->description('Project ID (required for update and delete)'),
            'name' => $schema->string()->description('Project name (required for create)')->max(255),
            'type' => $schema->string()->description('Project type: personal or freelance (update moves the project between modules)')->enum(['personal', 'freelance']),
            'client_id' => $schema->integer()->description('Client ID; required to move a project to freelance'),
            'description' => $schema->string()->description('Description (Markdown is converted to editable blocks)'),
            'status' => $schema->string()->description('Status: pending, in_progress, completed, cancelled, maintenance, archived'),
            'start_date' => $schema->string()->description('Start date in YYYY-MM-DD format'),
            'end_date' => $schema->string()->description('End date in YYYY-MM-DD format'),
            'deadline' => $schema->string()->description('Deadline in YYYY-MM-DD format'),
            'priority' => $schema->string()->description('Priority: Low, Normal, High, Urgent'),
            'urgency' => $schema->string()->description('Urgency: Low, Normal, High'),
            'importance' => $schema->string()->description('Importance: Low, Normal, High'),
            'budget' => $schema->number()->description('Budget')->min(0),
            'area' => $schema->string()->description('Area label'),
            'module' => $schema->string()->description('Free-form module label'),
            'tags' => $schema->array()->description('Tags')->items($schema->string()),
            'color' => $schema->string()->description('Hex color'),
            'icon' => $schema->string()->description('Icon label'),
            'notes' => $schema->string()->description('Notes'),
            'is_archived' => $schema->boolean()->description('Archive flag (update)'),
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:personal,freelance'],
            'client_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:50'],
            'urgency' => ['nullable', 'string', 'max:50'],
            'importance' => ['nullable', 'string', 'max:50'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'area' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = $this->projects->create($user, [
            ...$request->only([
                'name', 'client_id', 'status', 'deadline', 'start_date', 'end_date',
                'priority', 'urgency', 'importance', 'budget', 'area', 'module',
                'tags', 'color', 'icon', 'notes',
            ]),
            'type' => $request->get('type', 'personal'),
            'description' => $this->markdownDescription($request->get('description')),
        ]);

        return Response::structured([
            'project' => $project->fresh(),
            'message' => 'Project created successfully.',
        ]);
    }

    private function update(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request->get('project_id', 0))
            ->where('user_id', $user->id)
            ->where('type', 'personal')
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $data = array_filter([
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'client_id' => $request->get('client_id'),
            'status' => $request->get('status'),
            'deadline' => $request->get('deadline'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'priority' => $request->get('priority'),
            'urgency' => $request->get('urgency'),
            'importance' => $request->get('importance'),
            'budget' => $request->get('budget'),
            'area' => $request->get('area'),
            'module' => $request->get('module'),
            'tags' => $request->get('tags'),
            'color' => $request->get('color'),
            'icon' => $request->get('icon'),
            'notes' => $request->get('notes'),
        ], fn ($value) => $value !== null);

        if ($request->get('description') !== null) {
            $data['description'] = $this->markdownDescription($request->get('description'));
        }

        if ($request->get('is_archived') !== null) {
            $data['is_archived'] = (bool) $request->get('is_archived');
        }

        $project = $this->projects->update($user, $project, $data);

        return Response::structured([
            'project' => $project->fresh(),
            'message' => $project->type === 'freelance'
                ? 'Project moved to freelance successfully.'
                : 'Project updated successfully.',
        ]);
    }

    private function delete(Request $request, $user): Response|ResponseFactory
    {
        $project = Project::where('id', $request->get('project_id', 0))
            ->where('user_id', $user->id)
            ->where('type', 'personal')
            ->first();

        if (! $project) {
            return Response::error('Project not found or unauthorized.');
        }

        $project->delete();

        return Response::structured([
            'message' => 'Project deleted successfully.',
        ]);
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
}
