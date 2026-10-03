<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Health\Enums\MeasurementType;
use App\Models\HealthAppointment;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthStudy;
use App\Models\HealthStudyResult;
use App\Models\HealthSymptom;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class HealthQueryTool implements Tool
{
    /** @var array<int, string> */
    private const RESOURCES = [
        'conditions',
        'medications',
        'intakes',
        'measurements',
        'symptoms',
        'professionals',
        'studies',
        'study_results',
        'appointments',
        'summary',
    ];

    /** @var array<int, string> */
    private const PERSON_RESOURCES = ['conditions', 'medications', 'measurements', 'symptoms'];

    public function __construct(
        protected User $user,
        protected HealthService $health,
    ) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s health record: conditions, medications and intakes, measurements, symptoms, professionals, studies and results, appointments, or a summary of the active record. Read-only; it only reports what the user has recorded and never diagnoses.';
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = (string) ($request['resource'] ?? 'summary');

        if ($resource === 'summary') {
            return json_encode(['summary' => $this->health->summary($this->user)], JSON_PRETTY_PRINT);
        }

        $query = match ($resource) {
            'conditions' => HealthCondition::query()
                ->where('user_id', $this->user->id)
                ->with(['person:id,first_name,last_name', 'provider:id,name']),
            'medications' => HealthMedication::query()
                ->where('user_id', $this->user->id)
                ->with(['person:id,first_name,last_name', 'condition:id,name']),
            'intakes' => HealthMedicationIntake::query()
                ->where('user_id', $this->user->id)
                ->with('medication:id,name'),
            'measurements' => HealthMeasurement::query()
                ->where('user_id', $this->user->id)
                ->with('person:id,first_name,last_name'),
            'symptoms' => HealthSymptom::query()
                ->where('user_id', $this->user->id)
                ->with('person:id,first_name,last_name'),
            'professionals' => HealthProfessional::query()->where('user_id', $this->user->id),
            'studies' => HealthStudy::query()
                ->where('user_id', $this->user->id)
                ->with(['person:id,first_name,last_name', 'provider:id,name', 'condition:id,name'])
                ->withCount('results'),
            'study_results' => HealthStudyResult::query()
                ->whereHas('study', fn ($q) => $q->where('user_id', $this->user->id))
                ->with('study:id,user_id,title,performed_at'),
            'appointments' => HealthAppointment::query()
                ->where('user_id', $this->user->id)
                ->with(['person:id,first_name,last_name', 'provider:id,name']),
            default => null,
        };

        if (! $query) {
            return json_encode(['error' => "Invalid resource: {$resource}"], JSON_PRETTY_PRINT);
        }

        if ($search = $request['search'] ?? null) {
            $column = match ($resource) {
                'conditions', 'medications', 'professionals' => 'name',
                'symptoms' => 'symptom',
                'studies' => 'title',
                'study_results' => 'analyte',
                'appointments' => 'title',
                default => null,
            };

            if ($column) {
                $query->where($column, 'like', "%{$search}%");
            }
        }

        if (($personId = $request['person_id'] ?? null) && in_array($resource, self::PERSON_RESOURCES, true)) {
            $query->where('person_id', (int) $personId);
        }

        if ($resource === 'medications' && ($request['active'] ?? null) !== null) {
            $query->where('is_active', filter_var($request['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if ($resource === 'measurements' && ($request['type'] ?? null)) {
            $query->where('type', $request['type']);
        }

        if ($days = (int) ($request['days'] ?? 0)) {
            $column = match ($resource) {
                'measurements' => 'measured_at',
                'symptoms' => 'occurred_at',
                'intakes' => 'taken_at',
                'study_results' => 'created_at',
                'appointments' => 'scheduled_at',
                default => null,
            };

            if ($column) {
                $query->where($column, '>=', now()->subDays($days));
            }
        }

        match ($resource) {
            'measurements' => $query->latest('measured_at')->latest('id'),
            'symptoms' => $query->latest('occurred_at')->latest('id'),
            'intakes' => $query->latest('taken_at')->latest('id'),
            'studies' => $query->latest('performed_at')->latest('id'),
            'appointments' => $query->latest('scheduled_at')->latest('id'),
            default => $query->latest('id'),
        };

        $limit = min(max((int) ($request['limit'] ?? 20), 1), 100);
        $records = $query->limit($limit)->get();

        return json_encode([
            'records' => $records->toArray(),
            'count' => $records->count(),
            'limit' => $limit,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()
                ->description('Record set to read (default summary)')
                ->enum(self::RESOURCES),
            'person_id' => $schema->integer()->description('Only records linked to this person'),
            'active' => $schema->boolean()->description('Only active medications when true/false is provided'),
            'type' => $schema->string()->description('Only measurements of this type')->enum(MeasurementType::values()),
            'search' => $schema->string()->description('Search conditions, medications and professionals by name; symptoms by text'),
            'days' => $schema->integer()->description('Only measurements, symptoms and intakes from the last N days')->min(1)->max(3650),
            'limit' => $schema->integer()->description('Maximum records (default 20)')->min(1)->max(100),
        ];
    }
}
