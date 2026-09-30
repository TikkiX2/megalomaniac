<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\ProfessionalType;
use App\Health\Enums\Severity;
use App\Models\HealthProfessional;
use App\Services\Health\HealthService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class HealthWriteTool extends Tool
{
    protected string $name = 'health-write';

    protected string $description = 'Create, update or delete health conditions, medications and professionals, and log measurements, symptoms and medication intakes for the authenticated user. Use health-log for quick logging of events. It never diagnoses.';

    public function __construct(protected HealthService $health) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform')->enum([
                'create_condition', 'update_condition', 'delete_condition',
                'create_medication', 'update_medication', 'delete_medication',
                'create_professional', 'update_professional', 'delete_professional',
                'log_measurement', 'log_symptom', 'log_intake',
            ])->required(),
            'condition_id' => $schema->integer()->description('Condition ID (update_condition, delete_condition, create_medication, update_medication)'),
            'medication_id' => $schema->integer()->description('Medication ID (update_medication, delete_medication, log_intake)'),
            'professional_id' => $schema->integer()->description('Professional ID (update_professional, delete_professional)'),
            'kind' => $schema->string()->description('Condition kind (create_condition, update_condition)')->enum(ConditionKind::values()),
            'name' => $schema->string()->description('Condition, medication or professional name')->max(255),
            'status' => $schema->string()->description('Condition status (condition actions) or intake status (log_intake)')->enum([
                ...ConditionStatus::values(),
                ...IntakeStatus::values(),
            ]),
            'severity' => $schema->string()->description('Condition or symptom severity')->enum(Severity::values()),
            'diagnosed_at' => $schema->string()->description('Diagnosis date (YYYY-MM-DD)'),
            'provider_id' => $schema->integer()->description('Health professional ID as condition provider'),
            'person_id' => $schema->integer()->description('Person ID the record belongs to'),
            'dose_amount' => $schema->number()->description('Medication dose amount'),
            'dose_unit' => $schema->string()->description('Medication dose unit')->max(30),
            'route' => $schema->string()->description('Medication route')->max(30),
            'frequency_text' => $schema->string()->description('Medication frequency (e.g. every 8 hours)')->max(255),
            'started_at' => $schema->string()->description('Medication start date (YYYY-MM-DD)'),
            'ended_at' => $schema->string()->description('Medication end date (YYYY-MM-DD)'),
            'is_active' => $schema->boolean()->description('Active flag (medications and professionals)'),
            'prescriber_id' => $schema->integer()->description('Health professional ID as prescriber'),
            'type' => $schema->string()->description('Measurement type (log_measurement) or professional type (create_professional, update_professional)')->enum([
                ...ProfessionalType::values(),
                ...MeasurementType::values(),
            ]),
            'specialty' => $schema->string()->description('Professional specialty')->max(255),
            'phone' => $schema->string()->description('Professional phone')->max(50),
            'email' => $schema->string()->description('Professional email')->max(255),
            'address' => $schema->string()->description('Professional address')->max(255),
            'value' => $schema->number()->description('Measurement value (log_measurement)'),
            'secondary_value' => $schema->number()->description('Second measurement value (e.g. diastolic pressure)'),
            'unit' => $schema->string()->description('Measurement unit (log_measurement)')->max(20),
            'measured_at' => $schema->string()->description('Measurement timestamp (defaults to now)'),
            'symptom' => $schema->string()->description('Symptom text (log_symptom)')->max(255),
            'occurred_at' => $schema->string()->description('Symptom timestamp (defaults to now)'),
            'taken_at' => $schema->string()->description('Intake timestamp (defaults to now)'),
            'notes' => $schema->string()->description('Notes'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action', '');
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create_condition' => $this->createCondition($request, $user),
                'update_condition' => $this->updateCondition($request, $user),
                'delete_condition' => $this->deleteCondition($request, $user),
                'create_medication' => $this->createMedication($request, $user),
                'update_medication' => $this->updateMedication($request, $user),
                'delete_medication' => $this->deleteMedication($request, $user),
                'create_professional' => $this->createProfessional($request, $user),
                'update_professional' => $this->updateProfessional($request, $user),
                'delete_professional' => $this->deleteProfessional($request, $user),
                'log_measurement' => $this->logMeasurement($request, $user),
                'log_symptom' => $this->logSymptom($request, $user),
                'log_intake' => $this->logIntake($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createCondition(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'kind' => ['required', Rule::enum(ConditionKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $condition = $this->health->createCondition($user, array_filter($request->all([
            'kind', 'name', 'status', 'severity', 'diagnosed_at', 'provider_id', 'person_id', 'notes',
        ]), fn ($value) => $value !== null));

        return Response::structured([
            'condition' => $condition->toArray(),
            'message' => 'Condition created successfully.',
        ]);
    }

    private function updateCondition(Request $request, $user): Response|ResponseFactory
    {
        $condition = $this->health->findCondition($user, (int) $request->get('condition_id', 0));

        $request->validate([
            'kind' => ['sometimes', Rule::enum(ConditionKind::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $condition = $this->health->updateCondition($user, $condition, array_filter($request->all([
            'kind', 'name', 'status', 'severity', 'diagnosed_at', 'provider_id', 'person_id', 'notes',
        ]), fn ($value) => $value !== null));

        return Response::structured([
            'condition' => $condition->toArray(),
            'message' => 'Condition updated successfully.',
        ]);
    }

    private function deleteCondition(Request $request, $user): Response|ResponseFactory
    {
        $condition = $this->health->findCondition($user, (int) $request->get('condition_id', 0));
        $this->health->deleteCondition($user, $condition);

        return Response::structured(['message' => 'Condition deleted successfully.']);
    }

    private function createMedication(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency_text' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $user->id)],
            'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = array_filter($request->all([
            'name', 'dose_amount', 'dose_unit', 'route', 'frequency_text', 'started_at',
            'ended_at', 'condition_id', 'prescriber_id', 'person_id', 'notes',
        ]), fn ($value) => $value !== null);

        if ($request->get('is_active') !== null) {
            $data['is_active'] = (bool) $request->get('is_active');
        }

        $medication = $this->health->createMedication($user, $data);

        return Response::structured([
            'medication' => $medication->toArray(),
            'message' => 'Medication created successfully.',
        ]);
    }

    private function updateMedication(Request $request, $user): Response|ResponseFactory
    {
        $medication = $this->health->findMedication($user, (int) $request->get('medication_id', 0));

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency_text' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $user->id)],
            'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = array_filter($request->all([
            'name', 'dose_amount', 'dose_unit', 'route', 'frequency_text', 'started_at',
            'ended_at', 'condition_id', 'prescriber_id', 'person_id', 'notes',
        ]), fn ($value) => $value !== null);

        if ($request->get('is_active') !== null) {
            $data['is_active'] = (bool) $request->get('is_active');
        }

        $medication = $this->health->updateMedication($user, $medication, $data);

        return Response::structured([
            'medication' => $medication->toArray(),
            'message' => 'Medication updated successfully.',
        ]);
    }

    private function deleteMedication(Request $request, $user): Response|ResponseFactory
    {
        $medication = $this->health->findMedication($user, (int) $request->get('medication_id', 0));
        $this->health->deleteMedication($user, $medication);

        return Response::structured(['message' => 'Medication deleted successfully.']);
    }

    private function createProfessional(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'type' => ['required', Rule::enum(ProfessionalType::class)],
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data = array_filter($request->all([
            'type', 'name', 'specialty', 'phone', 'email', 'address', 'notes',
        ]), fn ($value) => $value !== null);

        if ($request->get('is_active') !== null) {
            $data['is_active'] = (bool) $request->get('is_active');
        }

        $professional = $this->health->createProfessional($user, $data);

        return Response::structured([
            'professional' => $professional->toArray(),
            'message' => 'Professional created successfully.',
        ]);
    }

    private function updateProfessional(Request $request, $user): Response|ResponseFactory
    {
        $professional = HealthProfessional::where('user_id', $user->id)
            ->find((int) $request->get('professional_id', 0));

        if (! $professional) {
            return Response::error('HealthProfessional not found.');
        }

        $request->validate([
            'type' => ['sometimes', Rule::enum(ProfessionalType::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data = array_filter($request->all([
            'type', 'name', 'specialty', 'phone', 'email', 'address', 'notes',
        ]), fn ($value) => $value !== null);

        if ($request->get('is_active') !== null) {
            $data['is_active'] = (bool) $request->get('is_active');
        }

        $professional = $this->health->updateProfessional($user, $professional, $data);

        return Response::structured([
            'professional' => $professional->toArray(),
            'message' => 'Professional updated successfully.',
        ]);
    }

    private function deleteProfessional(Request $request, $user): Response|ResponseFactory
    {
        $professional = HealthProfessional::where('user_id', $user->id)
            ->find((int) $request->get('professional_id', 0));

        if (! $professional) {
            return Response::error('HealthProfessional not found.');
        }

        $this->health->deleteProfessional($user, $professional);

        return Response::structured(['message' => 'Professional deleted successfully.']);
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
            'severity' => ['required', Rule::enum(Severity::class)],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $user->id)],
        ]);

        $data = array_filter($request->all([
            'symptom', 'severity', 'notes', 'person_id',
        ]), fn ($value) => $value !== null);

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
