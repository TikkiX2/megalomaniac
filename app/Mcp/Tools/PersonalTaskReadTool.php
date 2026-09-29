<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\ProjectTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PersonalTaskReadTool extends Tool
{
    protected string $name = 'personal-task-read';

    protected string $description = 'Read the authenticated user\'s personal tasks: standalone tasks plus tasks inside personal projects.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum records to return (default: 20)')->min(1)->max(100),
            'status' => $schema->string()->description('Filter by status: Pending, In Progress, Done, Archived'),
            'priority' => $schema->string()->description('Filter by priority: low, medium, high'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $limit = (int) $request->get('limit', 20);
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $query = ProjectTask::where('user_id', $user->id)
            ->where(function ($scope) {
                $scope->whereNull('project_id')
                    ->orWhereHas('project', fn ($project) => $project->where('type', 'personal'));
            })
            ->with(['properties', 'project']);

        $status = $request->get('status');

        if ($status) {
            $query->where('status', $status);
        }

        $priority = $request->get('priority');

        if ($priority) {
            $query->where('priority', $priority);
        }

        $tasks = $query->latest()->limit($limit)->get();

        return Response::structured([
            'records' => $tasks->all(),
            'count' => $tasks->count(),
            'limit' => $limit,
        ]);
    }
}
