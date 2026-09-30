<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Models\HealthCondition;
use App\Models\HealthProfessional;
use App\Models\Person;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConditionController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        $query = HealthCondition::query()
            ->where('user_id', $request->user()->id)
            ->with(['person:id,first_name,last_name', 'provider:id,name']);

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));
        $query->when($request->status, fn ($q, $status) => $q->where('status', $status));
        $query->when($request->kind, fn ($q, $kind) => $q->where('kind', $kind));

        return Inertia::render('health/conditions/Index', [
            'conditions' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'kind']),
            'statusOptions' => ConditionStatus::values(),
            'kindOptions' => ConditionKind::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('health/conditions/Form', $this->formProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->health->createCondition($request->user(), $request->validate($this->rules($request)));

        return redirect()->route('health.conditions.index')->with('success', 'Condición creada.');
    }

    public function edit(Request $request, HealthCondition $condition): Response
    {
        $this->authorize('update', $condition);

        return Inertia::render('health/conditions/Form', [
            'condition' => $condition->load('person:id,first_name,last_name'),
            ...$this->formProps($request),
        ]);
    }

    public function update(Request $request, HealthCondition $condition): RedirectResponse
    {
        $this->authorize('update', $condition);

        $this->health->updateCondition($request->user(), $condition, $request->validate($this->rules($request, partial: true)));

        return redirect()->route('health.conditions.index')->with('success', 'Condición actualizada.');
    }

    public function destroy(Request $request, HealthCondition $condition): RedirectResponse
    {
        $this->authorize('delete', $condition);

        $this->health->deleteCondition($request->user(), $condition);

        return redirect()->route('health.conditions.index')->with('success', 'Condición eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Request $request): array
    {
        return [
            'kindOptions' => ConditionKind::values(),
            'statusOptions' => ConditionStatus::values(),
            'severityOptions' => Severity::values(),
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'providers' => HealthProfessional::where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $partial = false): array
    {
        return [
            'kind' => [$partial ? 'sometimes' : 'required', Rule::enum(ConditionKind::class)],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'status' => [$partial ? 'sometimes' : 'required', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $request->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
