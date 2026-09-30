<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\MeasurementType;
use App\Http\Controllers\Controller;
use App\Models\HealthMeasurement;
use App\Models\Person;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeasurementController extends Controller
{
    /** @var array<string, string> */
    private const UNIT_SUGGESTIONS = [
        'weight' => 'kg',
        'blood_pressure' => 'mmHg',
        'heart_rate' => 'bpm',
        'glucose' => 'mg/dL',
        'temperature' => '°C',
        'oxygen_saturation' => '%',
        'waist' => 'cm',
    ];

    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        $query = HealthMeasurement::query()
            ->where('user_id', $request->user()->id)
            ->with('person:id,first_name,last_name');

        $query->when($request->type, fn ($q, $type) => $q->where('type', $type));

        $selectedType = MeasurementType::tryFrom((string) ($request->type ?? '')) ?? MeasurementType::Weight;

        return Inertia::render('health/measurements/Index', [
            'measurements' => $query->latest('measured_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'chart' => $this->chart($request, $selectedType),
            'filters' => $request->only(['type']),
            'typeOptions' => MeasurementType::values(),
            'unitSuggestions' => self::UNIT_SUGGESTIONS,
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));

        $this->health->logMeasurement($request->user(), $data);

        $synced = ($data['type'] ?? null) === MeasurementType::Weight->value && empty($data['person_id']);

        return redirect()->route('health.measurements.index')
            ->with('success', $this->weightMessage('Medición registrada.', $synced));
    }

    public function update(Request $request, HealthMeasurement $measurement): RedirectResponse
    {
        $this->authorize('update', $measurement);

        $wasPersonalWeight = $this->isPersonalWeight($measurement);

        $measurement = $this->health->updateMeasurement(
            $request->user(),
            $measurement,
            $request->validate($this->rules($request, partial: true)),
        );

        $synced = $wasPersonalWeight || $this->isPersonalWeight($measurement);

        return redirect()->route('health.measurements.index')
            ->with('success', $this->weightMessage('Medición actualizada.', $synced));
    }

    public function destroy(Request $request, HealthMeasurement $measurement): RedirectResponse
    {
        $this->authorize('delete', $measurement);

        $this->health->deleteMeasurement($request->user(), $measurement);

        return redirect()->route('health.measurements.index')->with('success', 'Medición eliminada.');
    }

    /**
     * @return array<int, array{date: string, value: float}>
     */
    private function chart(Request $request, MeasurementType $type): array
    {
        return HealthMeasurement::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('person_id')
            ->where('type', $type->value)
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get(['measured_at', 'value'])
            ->reverse()
            ->values()
            ->map(fn (HealthMeasurement $measurement): array => [
                'date' => $measurement->measured_at->format('Y-m-d'),
                'value' => (float) $measurement->value,
            ])
            ->all();
    }

    private function isPersonalWeight(HealthMeasurement $measurement): bool
    {
        return $measurement->type === MeasurementType::Weight && $measurement->person_id === null;
    }

    private function weightMessage(string $message, bool $synced): string
    {
        return $synced ? $message.' Actualizó tu peso de perfil.' : $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $partial = false): array
    {
        return [
            'type' => [$partial ? 'sometimes' : 'required', Rule::enum(MeasurementType::class)],
            'value' => [$partial ? 'sometimes' : 'required', 'numeric'],
            'secondary_value' => ['nullable', 'numeric'],
            'unit' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
            'measured_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
        ];
    }
}
