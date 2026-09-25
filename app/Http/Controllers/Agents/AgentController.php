<?php

namespace App\Http\Controllers\Agents;

use App\Ai\Agents\AgentDefinitionService;
use App\Ai\Agents\AgentScheduler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Jobs\RunAgentJob;
use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\Connection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private readonly AgentDefinitionService $service,
    ) {}

    public function index(Request $request): Response
    {
        $agents = AgentDefinition::query()
            ->forUser($request->user())
            ->withCount('agentRuns')
            ->orderBy('name')
            ->get()
            ->map(fn (AgentDefinition $definition): array => [
                'id' => $definition->id,
                'key' => $definition->key,
                'name' => $definition->name,
                'description' => $definition->description,
                'enabled' => $definition->enabled,
                'schedule_type' => $definition->schedule_type,
                'schedule_value' => $definition->schedule_value,
                'tools_policy' => $definition->tools_policy,
                'next_run_at' => $definition->next_run_at?->toIso8601String(),
                'last_run_at' => $definition->last_run_at?->toIso8601String(),
                'failure_count' => $definition->failure_count,
                'runs_count' => $definition->agent_runs_count,
                'last_status' => $definition->agentRuns()->latest()->value('status'),
            ])
            ->values();

        return Inertia::render('agents/index', [
            'agents' => $agents,
            'scheduleIntervals' => array_keys(AgentScheduler::INTERVALS),
            'internalTools' => [
                ['key' => 'workout_query', 'label' => 'Entrenamientos'],
                ['key' => 'finance_query', 'label' => 'Finanzas'],
                ['key' => 'nutrition_query', 'label' => 'Nutrición'],
                ['key' => 'grocery_query', 'label' => 'Compras'],
                ['key' => 'actions', 'label' => 'Acciones internas (crear registros)'],
            ],
            'integrations' => Connection::query()
                ->forUser($request->user())
                ->enabled()
                ->orderBy('name')
                ->get()
                ->map(fn ($connection): array => ['kind' => $connection->kind, 'name' => $connection->name])
                ->unique('kind')
                ->values(),
        ]);
    }

    public function show(Request $request, int $agent): Response
    {
        $definition = $this->owned($request, $agent);

        $runs = $definition->agentRuns()
            ->latest()
            ->paginate(15)
            ->through(fn (AgentRun $run): array => [
                'id' => $run->id,
                'status' => $run->status,
                'triggered_by' => $run->triggered_by,
                'report' => $run->report,
                'error' => $run->error,
                'suggestions_created' => $run->suggestions_created,
                'approvals_created' => $run->approvals_created,
                'usage' => $run->usage,
                'created_at' => $run->created_at?->toIso8601String(),
            ]);

        return Inertia::render('agents/show', [
            'agent' => [
                'id' => $definition->id,
                'key' => $definition->key,
                'name' => $definition->name,
                'description' => $definition->description,
                'instructions' => $definition->instructions,
                'enabled' => $definition->enabled,
                'schedule_type' => $definition->schedule_type,
                'schedule_value' => $definition->schedule_value,
                'tools_policy' => $definition->tools_policy,
                'next_run_at' => $definition->next_run_at?->toIso8601String(),
                'failure_count' => $definition->failure_count,
            ],
            'runs' => $runs,
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->user(), $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return to_route('agents.index')->with('success', 'Agente creado.');
    }

    public function update(UpdateAgentRequest $request, int $agent): RedirectResponse
    {
        $definition = $this->owned($request, $agent);

        try {
            $this->service->update($definition, $request->validated());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Agente actualizado.');
    }

    public function toggle(Request $request, int $agent): RedirectResponse
    {
        $definition = $this->owned($request, $agent);

        $this->service->toggle($definition, ! $definition->enabled);

        return back()->with('success', $definition->fresh()->enabled ? 'Agente habilitado.' : 'Agente deshabilitado.');
    }

    public function run(Request $request, int $agent): RedirectResponse
    {
        $definition = $this->owned($request, $agent);

        RunAgentJob::dispatch($definition->id, 'manual');

        return back()->with('success', 'Ejecución encolada.');
    }

    public function destroy(Request $request, int $agent): RedirectResponse
    {
        $this->owned($request, $agent)->delete();

        return to_route('agents.index')->with('success', 'Agente eliminado.');
    }

    protected function owned(Request $request, int $agentId): AgentDefinition
    {
        return AgentDefinition::query()
            ->forUser($request->user())
            ->findOrFail($agentId);
    }
}
