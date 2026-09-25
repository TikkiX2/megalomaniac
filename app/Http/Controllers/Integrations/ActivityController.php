<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'connection_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'action_key' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = IntegrationActionLog::query()
            ->where('user_id', $request->user()->id)
            ->when($filters['connection_id'] ?? null, fn ($query, $id) => $query->where('connection_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['action_key'] ?? null, fn ($query, $key) => $query->where('action_key', 'like', "%{$key}%"))
            ->with('connection')
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (IntegrationActionLog $log): array => [
                'id' => $log->id,
                'connection_id' => $log->connection_id,
                'connection_name' => $log->connection?->name,
                'action_key' => $log->action_key,
                'access' => $log->access->value,
                'source' => $log->source,
                'status' => $log->status,
                'result_summary' => $log->result_summary,
                'error' => $log->error,
                'duration_ms' => $log->duration_ms,
                'params' => $log->params,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        $connections = Connection::query()
            ->forUser($request->user())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Connection $connection): array => [
                'id' => $connection->id,
                'name' => $connection->name,
            ]);

        return Inertia::render('integrations/activity', [
            'logs' => $logs,
            'connections' => $connections,
            'filters' => $filters,
        ]);
    }
}
