<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\IntakeStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthCondition;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\Person;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MedicationController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        $query = HealthMedication::query()
            ->where('user_id', $request->user()->id)
            ->with('condition:id,name')
            ->withCount('intakes')
            ->withMax('intakes', 'taken_at')
            ->addSelect([
                'last_intake_id' => HealthMedicationIntake::query()
                    ->select('id')
                    ->whereColumn('medication_id', 'health_medications.id')
                    ->orderByDesc('taken_at')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return Inertia::render('health/medications/Index', [
            'medications' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'active']),
            'statusOptions' => IntakeStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('health/medications/Form', $this->formProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->health->createMedication($request->user(), $request->validate($this->rules($request)));

        return redirect()->route('health.medications.index')->with('success', 'Medicación creada.');
    }

    public function edit(Request $request, HealthMedication $medication): Response
    {
        $this->authorize('update', $medication);

        return Inertia::render('health/medications/Form', [
            'medication' => $medication->load('condition:id,name'),
            ...$this->formProps($request),
        ]);
    }

    public function update(Request $request, HealthMedication $medication): RedirectResponse
    {
        $this->authorize('update', $medication);

        $this->health->updateMedication($request->user(), $medication, $request->validate($this->rules($request, partial: true)));

        return redirect()->route('health.medications.index')->with('success', 'Medicación actualizada.');
    }

    public function destroy(Request $request, HealthMedication $medication): RedirectResponse
    {
        $this->authorize('delete', $medication);

        $this->health->deleteMedication($request->user(), $medication);

        return redirect()->route('health.medications.index')->with('success', 'Medicación eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Request $request): array
    {
        return [
            'conditions' => HealthCondition::where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name']),
            'providers' => HealthProfessional::where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name']),
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency_text' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['boolean'],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $request->user()->id)],
            'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $request->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
