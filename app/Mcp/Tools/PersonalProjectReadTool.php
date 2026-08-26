<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PersonalProjectReadTool extends Tool
{
    protected string $name = 'personal-project-read';

    protected string $description = 'Read the authenticated user\'s personal projects: list projects with tasks, milestones, and progress.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum records to return (default: 20)')->min(1)->max(100),
            'status' => $schema->string()->description('Filter by status: pending, in_progress, completed, archived')->enum(['pending', 'in_progress', 'completed', 'archived']),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $limit = (int) $request['limit'] ?? 20;
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $query = Project::where('user_id', $user->id)
            ->where('type', 'personal')
            ->with(['tasks', 'milestones'])
            ->withCount('tasks');

        if (isset($request['status'])) {
            $query->where('status', $request['status']);
        }

        $projects = $query->latest()->limit($limit)->get();

        return Response::structured([
            'records' => $projects->all(),
            'count' => $projects->count(),
            'limit' => $limit,
        ]);
    }
}
