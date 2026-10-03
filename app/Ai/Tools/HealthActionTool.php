<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Health\Enums\AppointmentStatus;
use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\ProfessionalType;
use App\Health\Enums\ResultFlag;
use App\Health\Enums\Severity;
use App\Health\Enums\StudyType;
use App\Models\HealthProfessional;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use ValueError;

class HealthActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected HealthService $health,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create, update or delete health conditions, medications, professionals, studies and appointments, and log measurements, symptoms and medication intakes on behalf of the user. Use this when the user asks to record or update health data. It only records what the user reports; it never diagnoses, interprets results or recommends treatments.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus datos de salud').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_condition' => 'crear una condición',
            'update_condition' => 'actualizar una condición',
            'delete_condition' => 'eliminar una condición',
            'create_medication' => 'crear un medicamento',
            'update_medication' => 'actualizar un medicamento',
            'delete_medication' => 'eliminar un medicamento',
            'create_professional' => 'crear un profesional',
            'update_professional' => 'actualizar un profesional',
            'delete_professional' => 'eliminar un profesional',
            'log_measurement' => 'registrar una medición',
            'log_symptom' => 'registrar un síntoma',
            'log_intake' => 'registrar una toma',
            'create_study' => 'crear un estudio',
            'update_study' => 'actualizar un estudio',
            'delete_study' => 'eliminar un estudio',
            'add_study_result' => 'añadir un resultado de estudio',
            'delete_study_result' => 'eliminar un resultado de estudio',
            'create_appointment' => 'crear una cita médica',
            'update_appointment' => 'actualizar una cita médica',
            'delete_appointment' => 'eliminar una cita médica',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_condition' => $this->createCondition($request),
                'update_condition' => $this->updateCondition($request),
                'delete_condition' => $this->deleteCondition($request),
                'create_medication' => $this->createMedication($request),
                'update_medication' => $this->updateMedication($request),
                'delete_medication' => $this->deleteMedication($request),
                'create_professional' => $this->createProfessional($request),
                'update_professional' => $this->updateProfessional($request),
                'delete_professional' => $this->deleteProfessional($request),
                'log_measurement' => $this->logMeasurement($request),
                'log_symptom' => $this->logSymptom($request),
                'log_intake' => $this->logIntake($request),
                'create_study' => $this->createStudy($request),
                'update_study' => $this->updateStudy($request),
                'delete_study' => $this->deleteStudy($request),
                'add_study_result' => $this->addStudyResult($request),
                'delete_study_result' => $this->deleteStudyResult($request),
                'create_appointment' => $this->createAppointment($request),
                'update_appointment' => $this->updateAppointment($request),
                'delete_appointment' => $this->deleteAppointment($request),
                default => $this->error('Invalid action. Use: create_condition, update_condition, delete_condition, create_medication, update_medication, delete_medication, create_professional, update_professional, delete_professional, log_measurement, log_symptom, log_intake, create_study, update_study, delete_study, add_study_result, delete_study_result, create_appointment, update_appointment, delete_appointment'),
            };
        } catch (ValidationException $exception) {
            return $this->error(implode(' ', array_merge(...array_values($exception->errors()))));
        } catch (ModelNotFoundException|ValueError|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function createCondition(Request $request): string
    {
        $data = $this->validated($request, [
            'kind' => ['required', Rule::enum(ConditionKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $condition = $this->health->createCondition($this->user, $this->withoutNulls($data));

        return $this->success('Condition created.', ['condition' => $condition->toArray()]);
    }

    private function updateCondition(Request $request): string
    {
        $condition = $this->health->findCondition($this->user, (int) ($request['condition_id'] ?? 0));

        $data = $this->validated($request, [
            'kind' => ['sometimes', Rule::enum(ConditionKind::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $this->withoutNulls($data);

        if ($data === []) {
            return $this->error('No fields to update.');
        }

        $condition = $this->health->updateCondition($this->user, $condition, $data);

        return $this->success('Condition updated.', ['condition' => $condition->toArray()]);
    }

    private function deleteCondition(Request $request): string
    {
        $condition = $this->health->findCondition($this->user, (int) ($request['condition_id'] ?? 0));
        $this->health->deleteCondition($this->user, $condition);

        return $this->success('Condition deleted.');
    }

    private function createMedication(Request $request): string
    {
        $data = $this->validated($request, [
            'name' => ['required', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency_text' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $this->user->id)],
            'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $medication = $this->health->createMedication($this->user, $this->withoutNulls($data));

        return $this->success('Medication created.', ['medication' => $medication->toArray()]);
    }

    private function updateMedication(Request $request): string
    {
        $medication = $this->health->findMedication($this->user, (int) ($request['medication_id'] ?? 0));

        $data = $this->validated($request, [
            'name' => ['sometimes', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency_text' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $this->user->id)],
            'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $this->withoutNulls($data);

        if ($data === []) {
            return $this->error('No fields to update.');
        }

        $medication = $this->health->updateMedication($this->user, $medication, $data);

        return $this->success('Medication updated.', ['medication' => $medication->toArray()]);
    }

    private function deleteMedication(Request $request): string
    {
        $medication = $this->health->findMedication($this->user, (int) ($request['medication_id'] ?? 0));
        $this->health->deleteMedication($this->user, $medication);

        return $this->success('Medication deleted.');
    }

    private function createProfessional(Request $request): string
    {
        $data = $this->validated($request, [
            'type' => ['required', Rule::enum(ProfessionalType::class)],
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $professional = $this->health->createProfessional($this->user, $this->withoutNulls($data));

        return $this->success('Professional created.', ['professional' => $professional->toArray()]);
    }

    private function updateProfessional(Request $request): string
    {
        $professional = HealthProfessional::query()
            ->where('user_id', $this->user->id)
            ->find((int) ($request['professional_id'] ?? 0));

        if (! $professional) {
            return $this->error('HealthProfessional not found.');
        }

        $data = $this->validated($request, [
            'type' => ['sometimes', Rule::enum(ProfessionalType::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data = $this->withoutNulls($data);

        if ($data === []) {
            return $this->error('No fields to update.');
        }

        $professional = $this->health->updateProfessional($this->user, $professional, $data);

        return $this->success('Professional updated.', ['professional' => $professional->toArray()]);
    }

    private function deleteProfessional(Request $request): string
    {
        $professional = HealthProfessional::query()
            ->where('user_id', $this->user->id)
            ->find((int) ($request['professional_id'] ?? 0));

        if (! $professional) {
            return $this->error('HealthProfessional not found.');
        }

        $this->health->deleteProfessional($this->user, $professional);

        return $this->success('Professional deleted.');
    }

    private function logMeasurement(Request $request): string
    {
        $data = $this->validated($request, [
            'type' => ['required', Rule::enum(MeasurementType::class)],
            'value' => ['required', 'numeric'],
            'secondary_value' => ['nullable', 'numeric'],
            'unit' => ['required', 'string', 'max:20'],
            'measured_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
        ]);

        $data = $this->withoutNulls($data);
        $data['measured_at'] = Carbon::parse($request['measured_at'] ?? now())->utc()->toDateTimeString();

        $measurement = $this->health->logMeasurement($this->user, $data);

        return $this->success('Measurement logged.', ['measurement' => $measurement->toArray()]);
    }

    private function logSymptom(Request $request): string
    {
        $data = $this->validated($request, [
            'symptom' => ['required', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
        ]);

        $data = $this->withoutNulls($data);
        $data['severity'] = $request['severity'] ?? Severity::Mild->value;
        $data['occurred_at'] = Carbon::parse($request['occurred_at'] ?? now())->utc()->toDateTimeString();

        $symptom = $this->health->logSymptom($this->user, $data);

        return $this->success('Symptom logged.', ['symptom' => $symptom->toArray()]);
    }

    private function logIntake(Request $request): string
    {
        $data = $this->validated($request, [
            'medication_id' => ['required', Rule::exists('health_medications', 'id')->where('user_id', $this->user->id)],
            'taken_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(IntakeStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $medication = $this->health->findMedication($this->user, (int) $data['medication_id']);

        $intake = $this->health->logIntake($this->user, $medication, [
            'taken_at' => Carbon::parse($request['taken_at'] ?? now())->utc()->toDateTimeString(),
            'status' => $data['status'] ?? IntakeStatus::Taken->value,
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success('Intake logged.', ['intake' => $intake->toArray()]);
    }

    private function createStudy(Request $request): string
    {
        $data = $this->validated($request, [
            'type' => ['required', Rule::enum(StudyType::class)],
            'title' => ['required', 'string', 'max:255'],
            'performed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $study = $this->health->createStudy($this->user, $this->withoutNulls($data));

        return $this->success('Study created.', ['study' => $study->toArray()]);
    }

    private function updateStudy(Request $request): string
    {
        $study = $this->health->findStudy($this->user, (int) ($request['study_id'] ?? 0));

        $data = $this->validated($request, [
            'type' => ['sometimes', Rule::enum(StudyType::class)],
            'title' => ['sometimes', 'string', 'max:255'],
            'performed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $this->withoutNulls($data);

        if ($data === []) {
            return $this->error('No fields to update.');
        }

        $study = $this->health->updateStudy($this->user, $study, $data);

        return $this->success('Study updated.', ['study' => $study->toArray()]);
    }

    private function deleteStudy(Request $request): string
    {
        $study = $this->health->findStudy($this->user, (int) ($request['study_id'] ?? 0));
        $this->health->deleteStudy($this->user, $study);

        return $this->success('Study deleted.');
    }

    private function addStudyResult(Request $request): string
    {
        $study = $this->health->findStudy($this->user, (int) ($request['study_id'] ?? 0));

        $data = $this->validated($request, [
            'analyte' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'flag' => ['nullable', Rule::enum(ResultFlag::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->health->createStudyResult($this->user, $study, $this->withoutNulls($data));

        return $this->success('Study result added.', ['result' => $result->toArray()]);
    }

    private function deleteStudyResult(Request $request): string
    {
        $study = $this->health->findStudy($this->user, (int) ($request['study_id'] ?? 0));
        $result = $study->results()
            ->find((int) ($request['result_id'] ?? 0));

        if (! $result) {
            return $this->error('HealthStudyResult not found.');
        }

        $this->health->deleteStudyResult($this->user, $result);

        return $this->success('Study result deleted.');
    }

    private function createAppointment(Request $request): string
    {
        $data = $this->validated($request, [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'scheduled_at' => ['required', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $this->withoutNulls($data);
        $data['status'] = $data['status'] ?? AppointmentStatus::Scheduled->value;
        $data['scheduled_at'] = Carbon::parse($data['scheduled_at'])->utc()->toDateTimeString();

        $appointment = $this->health->createAppointment($this->user, $data);

        return $this->success('Appointment created.', ['appointment' => $appointment->toArray()]);
    }

    private function updateAppointment(Request $request): string
    {
        $appointment = $this->health->findAppointment($this->user, (int) ($request['appointment_id'] ?? 0));

        $data = $this->validated($request, [
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(AppointmentStatus::class)],
            'scheduled_at' => ['sometimes', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user->id)],
            'notes' => ['nullable', 'string'],
        ]);

        $data = $this->withoutNulls($data);

        if (isset($data['scheduled_at'])) {
            $data['scheduled_at'] = Carbon::parse($data['scheduled_at'])->utc()->toDateTimeString();
        }

        if ($data === []) {
            return $this->error('No fields to update.');
        }

        $appointment = $this->health->updateAppointment($this->user, $appointment, $data);

        return $this->success('Appointment updated.', ['appointment' => $appointment->toArray()]);
    }

    private function deleteAppointment(Request $request): string
    {
        $appointment = $this->health->findAppointment($this->user, (int) ($request['appointment_id'] ?? 0));
        $this->health->deleteAppointment($this->user, $appointment);

        return $this->success('Appointment deleted.');
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        return Validator::make($request->all(), $rules)->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutNulls(array $data): array
    {
        return array_filter($data, fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform')->enum([
                'create_condition', 'update_condition', 'delete_condition',
                'create_medication', 'update_medication', 'delete_medication',
                'create_professional', 'update_professional', 'delete_professional',
                'log_measurement', 'log_symptom', 'log_intake',
                'create_study', 'update_study', 'delete_study',
                'add_study_result', 'delete_study_result',
                'create_appointment', 'update_appointment', 'delete_appointment',
            ])->required(),
            'condition_id' => $schema->integer()->description('Condition ID (update_condition, delete_condition, create_medication, update_medication)'),
            'medication_id' => $schema->integer()->description('Medication ID (update_medication, delete_medication, log_intake)'),
            'professional_id' => $schema->integer()->description('Professional ID (update_professional, delete_professional)'),
            'study_id' => $schema->integer()->description('Study ID (update_study, delete_study, add_study_result, delete_study_result)'),
            'result_id' => $schema->integer()->description('Study result ID (delete_study_result)'),
            'appointment_id' => $schema->integer()->description('Appointment ID (update_appointment, delete_appointment)'),
            'kind' => $schema->string()->description('Condition kind (create_condition, update_condition)')->enum(ConditionKind::values()),
            'name' => $schema->string()->description('Condition, medication or professional name')->max(255),
            'status' => $schema->string()->description('Condition status (condition actions), intake status (log_intake) or appointment status (appointment actions)')->enum([
                ...ConditionStatus::values(),
                ...IntakeStatus::values(),
                ...AppointmentStatus::values(),
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
            'type' => $schema->string()->description('Measurement type (log_measurement), professional type (create_professional, update_professional) or study type (create_study, update_study)')->enum([
                ...ProfessionalType::values(),
                ...MeasurementType::values(),
                ...StudyType::values(),
            ]),
            'title' => $schema->string()->description('Study or appointment title (study and appointment actions)')->max(255),
            'performed_at' => $schema->string()->description('Study performed date (YYYY-MM-DD)'),
            'scheduled_at' => $schema->string()->description('Appointment datetime (create_appointment, update_appointment)'),
            'analyte' => $schema->string()->description('Analyte/test name (add_study_result)')->max(255),
            'reference_range' => $schema->string()->description('Reference range text (add_study_result)')->max(255),
            'flag' => $schema->string()->description('Result flag (add_study_result)')->enum(ResultFlag::values()),
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
}
