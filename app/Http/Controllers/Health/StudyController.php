<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\StudyType;
use App\Http\Controllers\Controller;
use App\Models\HealthCondition;
use App\Models\HealthProfessional;
use App\Models\HealthStudy;
use App\Models\HealthStudyResult;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudyController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HealthStudy::query()
            ->where('user_id', $request->user()->id)
            ->with(['person:id,first_name,last_name', 'provider:id,name', 'condition:id,name']);

        $query->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"));
        $query->when($request->type, fn ($q, $type) => $q->where('type', $type));

        return Inertia::render('health/studies/Index', [
            'studies' => $query->latest('performed_at')->latest('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'type']),
            'typeOptions' => StudyType::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('health/studies/Form', $this->formProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HealthStudy::class);

        $data = $request->validate($this->rules($request));
        $data['user_id'] = $request->user()->id;

        HealthStudy::create($data);

        return redirect()->route('health.studies.index')->with('success', 'Estudio creado.');
    }

    public function show(Request $request, HealthStudy $study): Response
    {
        $this->authorize('view', $study);

        $study->load(['person:id,first_name,last_name', 'provider:id,name', 'condition:id,name', 'results']);

        // Historial de cada analito presente en este estudio, a lo largo de los
        // estudios del usuario (para el gráfico de evolución).
        $analytes = $study->results->pluck('analyte')->filter()->unique()->values();

        $evolution = [];
        if ($analytes->isNotEmpty()) {
            $rows = HealthStudyResult::query()
                ->whereIn('analyte', $analytes)
                ->with('study:id,user_id,performed_at')
                ->whereHas('study', fn ($q) => $q->where('user_id', $request->user()->id))
                ->orderBy('study_id')
                ->orderBy('sort_order')
                ->get()
                ->filter(fn ($r) => $r->study !== null);

            foreach ($rows->groupBy('analyte') as $analyte => $group) {
                $evolution[$analyte] = $group
                    ->map(fn ($r) => [
                        'date' => $r->study->performed_at?->toDateString() ?? $r->study->created_at->toDateString(),
                        'value' => (float) $r->value,
                        'unit' => $r->unit ?? '',
                        'flag' => $r->flag?->value,
                    ])
                    ->sortBy('date')
                    ->values()
                    ->all();
            }
        }

        return Inertia::render('health/studies/Show', [
            'study' => $study,
            'evolution' => $evolution,
        ]);
    }

    public function edit(Request $request, HealthStudy $study): Response
    {
        $this->authorize('update', $study);

        return Inertia::render('health/studies/Form', [
            'study' => $study->load(['person:id,first_name,last_name']),
            ...$this->formProps($request),
        ]);
    }

    public function update(Request $request, HealthStudy $study): RedirectResponse
    {
        $this->authorize('update', $study);

        $study->update($request->validate($this->rules($request, partial: true)));

        return redirect()->route('health.studies.index')->with('success', 'Estudio actualizado.');
    }

    public function destroy(Request $request, HealthStudy $study): RedirectResponse
    {
        $this->authorize('delete', $study);

        $study->delete();

        return redirect()->route('health.studies.index')->with('success', 'Estudio eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Request $request): array
    {
        return [
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'providers' => HealthProfessional::where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name']),
            'conditions' => HealthCondition::where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name']),
            'typeOptions' => StudyType::values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $partial = false): array
    {
        return [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', Rule::enum(StudyType::class)],
            'performed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $request->user()->id)],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $request->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
