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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class FreelanceReadTool extends Tool
{
    protected string $name = 'freelance-read';

    protected string $description = 'Read the authenticated user\'s freelance data: clients, projects, and tasks with optional filters.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Data type to read: clients, projects, or tasks (default: clients)')->enum(['clients', 'projects', 'tasks']),
            'limit' => $schema->integer()->description('Maximum records to return (default: 20)')->minimum(1)->maximum(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $type = (string) $request->get('type', 'clients');
        $limit = (int) $request->get('limit', 20);

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $records = match ($type) {
            'clients' => Client::where('user_id', $user->id)
                ->withCount('projects')
                ->latest()
                ->limit($limit)
                ->get(),
            'projects' => Project::where('user_id', $user->id)
                ->with(['client', 'currency'])
                ->withCount('tasks')
                ->latest()
                ->limit($limit)
                ->get(),
            'tasks' => ProjectTask::where('user_id', $user->id)
                ->with('project')
                ->latest()
                ->limit($limit)
                ->get(),
            default => null,
        };

        if (! $records) {
            return Response::error("Invalid type: {$type}");
        }

        return Response::structured([
            'type' => $type,
            'records' => $records->all(),
            'count' => $records->count(),
            'limit' => $limit,
        ]);
    }
}
