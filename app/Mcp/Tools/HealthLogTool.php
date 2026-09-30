<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\Severity;
use App\Services\Health\HealthService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class HealthLogTool extends Tool
{
    protected string $name = 'health-log';

    protected string $description = 'Quick-log health events. Only creates new records; never edits existing data.';

    public function __construct(protected HealthService $health) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->description('Event kind to log')->enum(['measurement', 'symptom', 'intake'])->required(),
            'type' => $schema->string()->description('Measurement type (kind measurement)')->enum(MeasurementType::values()),
            'value' => $schema->number()->description('Measurement value (kind measurement)'),
            'secondary_value' => $schema->number()->description('Second measurement value (e.g. diastolic pressure)'),
            'unit' => $schema->string()->description('Measurement unit (kind measurement)')->max(20),
            'measured_at' => $schema->string()->description('Measurement timestamp (defaults to now)'),
            'symptom' => $schema->string()->description('Symptom text (kind symptom)')->max(255),
            'severity' => $schema->string()->description('Symptom severity (kind symptom, default mild)')->enum(Severity::values()),
            'occurred_at' => $schema->string()->description('Symptom timestamp (defaults to now)'),
            'medication_id' => $schema->integer()->description('Medication ID (kind intake)'),
            'taken_at' => $schema->string()->description('Intake timestamp (defaults to now)'),
            'status' => $schema->string()->description('Intake status (kind intake, default taken)')->enum(IntakeStatus::values()),
            'person_id' => $schema->integer()->description('Person ID the event belongs to'),
            'notes' => $schema->string()->description('Notes'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $kind = (string) $request->get('kind', '');
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($kind) {
                'measurement' => $this->logMeasurement($request, $user),
                'symptom' => $this->logSymptom($request, $user),
                'intake' => $this->logIntake($request, $user),
                default => Response::error("Invalid kind: {$kind}"),
            };
        } catch (ModelNotFoundException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function logMeasurement(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'type' => ['required', Rule::enum(MeasurementType::class)],
            'value' => ['required', 'numeric'],
            'secondary_value' => ['nullable', 'numeric'],
            'unit' => ['required', 'string', 'max:20'],
            'measured_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
        ]);

        $data = array_filter($request->all([
            'type', 'value', 'secondary_value', 'unit', 'notes', 'person_id',
        ]), fn ($value) => $value !== null);

        $data['measured_at'] = Carbon::parse($request->get('measured_at') ?? now())->utc()->toDateTimeString();

        $measurement = $this->health->logMeasurement($user, $data);

        return Response::structured([
            'measurement' => $measurement->toArray(),
            'message' => 'Measurement logged successfully.',
        ]);
    }

    private function logSymptom(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'symptom' => ['required', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
        ]);

        $data = array_filter($request->all([
            'symptom', 'notes', 'person_id',
        ]), fn ($value) => $value !== null);

        $data['severity'] = $request->get('severity') ?? Severity::Mild->value;
        $data['occurred_at'] = Carbon::parse($request->get('occurred_at') ?? now())->utc()->toDateTimeString();

        $symptom = $this->health->logSymptom($user, $data);

        return Response::structured([
            'symptom' => $symptom->toArray(),
            'message' => 'Symptom logged successfully.',
        ]);
    }

    private function logIntake(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'medication_id' => ['required', Rule::exists('health_medications', 'id')->where('user_id', $user->id)],
            'taken_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(IntakeStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $medication = $this->health->findMedication($user, (int) $request->get('medication_id'));

        $intake = $this->health->logIntake($user, $medication, [
            'taken_at' => Carbon::parse($request->get('taken_at') ?? now())->utc()->toDateTimeString(),
            'status' => $request->get('status') ?? IntakeStatus::Taken->value,
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'intake' => $intake->toArray(),
            'message' => 'Intake logged successfully.',
        ]);
    }
}
