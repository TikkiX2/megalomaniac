<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FreelanceQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s freelance data: clients, projects, quotes and their project tasks. Use this before quoting or reporting on client work.';
    }

    public function handle(Request $request): Stringable|string
    {
        $type = (string) ($request['type'] ?? 'clients');
        $limit = (int) ($request['limit'] ?? 20);

        $data = match ($type) {
            'clients' => $this->user->clients()
                ->withCount(['projects' => fn ($query) => $query->where('type', 'freelance')])
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            'projects' => $this->user->projects()
                ->where('type', 'freelance')
                ->with('client')
                ->latest()
                ->limit($limit)
                ->get(),
            'quotes' => $this->user->quotes()
                ->with(['client', 'items'])
                ->latest()
                ->limit($limit)
                ->get(),
            'tasks' => $this->user->projectTasks()
                ->whereHas('project', fn ($query) => $query->where('type', 'freelance'))
                ->with('project')
                ->latest()
                ->limit($limit)
                ->get(),
            default => null,
        };

        if ($data === null) {
            return json_encode(['error' => 'Invalid type. Use: clients, projects, quotes, tasks']);
        }

        return json_encode([
            'type' => $type,
            'records' => $data->toArray(),
            'count' => $data->count(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['clients', 'projects', 'quotes', 'tasks'])
                ->description('Freelance data to query')
                ->default('clients'),
            'limit' => $schema->integer()->description('Maximum records to return (default 20)')->default(20),
        ];
    }
}
