<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Models\HealthSymptom;
use App\Models\Person;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SymptomController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        $query = HealthSymptom::query()
            ->where('user_id', $request->user()->id)
            ->with('person:id,first_name,last_name');

        $query->when($request->search, fn ($q, $search) => $q->where('symptom', 'like', "%{$search}%"));
        $query->when($request->severity, fn ($q, $severity) => $q->where('severity', $severity));

        return Inertia::render('health/symptoms/Index', [
            'symptoms' => $query->latest('occurred_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'severity']),
            'severityOptions' => Severity::values(),
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));
        $data['occurred_at'] = Carbon::parse($data['occurred_at'])->utc();

        $this->health->logSymptom($request->user(), $data);

        return redirect()->route('health.symptoms.index')->with('success', 'Síntoma registrado.');
    }

    public function update(Request $request, HealthSymptom $symptom): RedirectResponse
    {
        $this->authorize('update', $symptom);

        $data = $request->validate($this->rules($request, partial: true));

        if (array_key_exists('occurred_at', $data)) {
            $data['occurred_at'] = Carbon::parse($data['occurred_at'])->utc();
        }

        $this->health->updateSymptom($request->user(), $symptom, $data);

        return redirect()->route('health.symptoms.index')->with('success', 'Síntoma actualizado.');
    }

    public function destroy(Request $request, HealthSymptom $symptom): RedirectResponse
    {
        $this->authorize('delete', $symptom);

        $this->health->deleteSymptom($request->user(), $symptom);

        return redirect()->route('health.symptoms.index')->with('success', 'Síntoma eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $partial = false): array
    {
        return [
            'symptom' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'severity' => [$partial ? 'sometimes' : 'required', Rule::enum(Severity::class)],
            'occurred_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
        ];
    }
}
