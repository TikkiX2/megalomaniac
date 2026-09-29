<?php

namespace App\Ai\Tools;

use App\Ai\Support\MarkdownToYoopta;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectService;
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

class ProjectActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected ProjectService $projects,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create, update, move between modules, archive or delete the user\'s projects (personal or freelance). create_project requires an explicit type: if the user did not say which module, ask before creating. update_project with type moves the project between Personal and Freelance and remaps its board.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus proyectos').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_project' => 'crear un proyecto',
            'update_project' => 'actualizar o mover un proyecto',
            'archive_project' => 'archivar un proyecto',
            'delete_project' => 'eliminar un proyecto',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_project' => $this->create($request),
                'update_project' => $this->update($request),
                'archive_project' => $this->archive($request),
                'delete_project' => $this->delete($request),
                default => $this->error('Invalid action. Use: create_project, update_project, archive_project, delete_project'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function create(Request $request): string
    {
        $name = trim((string) ($request['name'] ?? ''));

        if ($name === '') {
            return $this->error('Project name is required.');
        }

        $type = $request['type'] ?? null;

        if (! in_array($type, ['personal', 'freelance'], true)) {
            return $this->error('Project type is required and must be personal or freelance. Ask the user which module the project belongs to before creating it.');
        }

        $project = $this->projects->create($this->user, [
            'name' => $name,
            'type' => $type,
            'client_id' => $request['client_id'] ?? null,
            'description' => $this->markdownDescription($request['description'] ?? null),
            'status' => $request['status'] ?? 'pending',
            'deadline' => $request['deadline'] ?? null,
            'priority' => $request['priority'] ?? null,
            'total_amount' => $request['total_amount'] ?? 0,
            'notes' => $request['notes'] ?? null,
        ]);

        return $this->success('Project created', [
            'project' => $project->only(['id', 'name', 'type', 'status', 'client_id', 'deadline']),
        ]);
    }

    private function update(Request $request): string
    {
        $project = $this->findProject($request['project_id'] ?? null);

        if (! $project) {
            return $this->error('Project not found');
        }

        $data = array_filter([
            'name' => $request['name'] ?? null,
            'type' => $request['type'] ?? null,
            'client_id' => $request['client_id'] ?? null,
            'status' => $request['status'] ?? null,
            'deadline' => $request['deadline'] ?? null,
            'priority' => $request['priority'] ?? null,
            'budget' => $request['budget'] ?? null,
            'area' => $request['area'] ?? null,
            'module' => $request['module'] ?? null,
            'tags' => $request['tags'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        if ($request->offsetExists('description')) {
            $data['description'] = $this->markdownDescription($request['description']);
        }

        if ($request->offsetExists('is_archived')) {
            $data['is_archived'] = (bool) $request['is_archived'];
        }

        $project = $this->projects->update($this->user, $project, $data);

        return $this->success('Project updated', [
            'project' => $project->only(['id', 'name', 'type', 'status', 'client_id', 'deadline', 'is_archived']),
        ]);
    }

    private function archive(Request $request): string
    {
        $project = $this->findProject($request['project_id'] ?? null);

        if (! $project) {
            return $this->error('Project not found');
        }

        $project = $this->projects->archive($this->user, $project);

        return $this->success('Project archived', [
            'project' => $project->only(['id', 'name', 'type', 'is_archived']),
        ]);
    }

    private function delete(Request $request): string
    {
        $project = $this->findProject($request['project_id'] ?? null);

        if (! $project) {
            return $this->error('Project not found');
        }

        $this->projects->delete($this->user, $project);

        return $this->success('Project deleted', [
            'project' => ['id' => $project->id, 'name' => $project->name],
        ]);
    }

    private function findProject(mixed $projectId): ?Project
    {
        if (! $projectId) {
            return null;
        }

        return $this->user->projects()->find((int) $projectId);
    }

    /**
     * Convert a plain-text or Markdown description into the Yoopta v4 block
     * map the editor stores, so model-written projects stay editable.
     *
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
                ->enum(['create_project', 'update_project', 'archive_project', 'delete_project'])
                ->description('Action to perform')
                ->required(),
            'project_id' => $schema->integer()->description('Project ID (update_project, archive_project, delete_project)'),
            'name' => $schema->string()->description('Project name (create_project, update_project)'),
            'type' => $schema->string()->description('Project type: personal or freelance. Required for create_project; changing it moves the project between modules (update_project).'),
            'client_id' => $schema->integer()->description('Client ID; required to create or move a project to freelance'),
            'description' => $schema->string()->description('Description (create_project, update_project); Markdown is welcome'),
            'status' => $schema->string()->description('Status (create_project, update_project)'),
            'deadline' => $schema->string()->description('Deadline YYYY-MM-DD (create_project, update_project)'),
            'priority' => $schema->string()->description('Priority (create_project, update_project)'),
            'total_amount' => $schema->number()->description('Total amount (create_project)'),
            'budget' => $schema->number()->description('Budget (update_project)'),
            'area' => $schema->string()->description('Area label (update_project)'),
            'module' => $schema->string()->description('Free-form module label (update_project)'),
            'tags' => $schema->array()->description('Tags (update_project)')->items($schema->string()),
            'notes' => $schema->string()->description('Notes (create_project, update_project)'),
            'is_archived' => $schema->boolean()->description('Archive flag (update_project)'),
        ];
    }
}
