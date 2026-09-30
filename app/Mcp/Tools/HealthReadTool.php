<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Health\Enums\MeasurementType;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Services\Health\HealthService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class HealthReadTool extends Tool
{
    /** @var array<int, string> */
    private const RESOURCES = [
        'conditions',
        'medications',
        'intakes',
        'measurements',
        'symptoms',
        'professionals',
        'summary',
    ];

    /** @var array<int, string> */
    private const PERSON_RESOURCES = ['conditions', 'medications', 'measurements', 'symptoms'];

    protected string $name = 'health-read';

    protected string $description = 'Read the authenticated user\'s health record: conditions, medications and intakes, measurements, symptoms and professionals, or a summary of the active record. Read-only; it never diagnoses and never writes.';

    public function __construct(protected HealthService $health) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('Record set to read (default summary)')->enum(self::RESOURCES),
            'person_id' => $schema->integer()->description('Only records linked to this person'),
            'active' => $schema->boolean()->description('Only active medications when false/true is provided'),
            'type' => $schema->string()->description('Only measurements of this type')->enum(MeasurementType::values()),
            'search' => $schema->string()->description('Search conditions, medications and professionals by name; symptoms by text'),
            'days' => $schema->integer()->description('Only measurements, symptoms and intakes from the last N days')->min(1)->max(3650),
            'limit' => $schema->integer()->description('Maximum records (default 20)')->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $request->validate([
            'resource' => ['sometimes', 'string', Rule::in(self::RESOURCES)],
            'person_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
            'type' => ['nullable', Rule::enum(MeasurementType::class)],
            'search' => ['nullable', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $resource = (string) $request->get('resource', 'summary');

        if ($resource === 'summary') {
            return Response::structured(['summary' => $this->health->summary($user)]);
        }

        $query = match ($resource) {
            'conditions' => HealthCondition::query()
                ->where('user_id', $user->id)
                ->with(['person:id,first_name,last_name', 'provider:id,name']),
            'medications' => HealthMedication::query()
                ->where('user_id', $user->id)
                ->with(['person:id,first_name,last_name', 'condition:id,name']),
            'intakes' => HealthMedicationIntake::query()
                ->where('user_id', $user->id)
                ->with('medication:id,name'),
            'measurements' => HealthMeasurement::query()
                ->where('user_id', $user->id)
                ->with('person:id,first_name,last_name'),
            'symptoms' => HealthSymptom::query()
                ->where('user_id', $user->id)
                ->with('person:id,first_name,last_name'),
            'professionals' => HealthProfessional::query()->where('user_id', $user->id),
            default => null,
        };

        if (! $query) {
            return Response::error("Invalid resource: {$resource}");
        }

        if ($search = $request->get('search')) {
            $column = match ($resource) {
                'conditions', 'medications', 'professionals' => 'name',
                'symptoms' => 'symptom',
                default => null,
            };

            if ($column) {
                $query->where($column, 'like', "%{$search}%");
            }
        }

        if ($personId = $request->get('person_id')) {
            if (in_array($resource, self::PERSON_RESOURCES, true)) {
                $query->where('person_id', (int) $personId);
            }
        }

        if ($resource === 'medications' && $request->get('active') !== null) {
            $query->where('is_active', filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($resource === 'measurements' && $request->get('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($days = (int) $request->get('days')) {
            $column = match ($resource) {
                'measurements' => 'measured_at',
                'symptoms' => 'occurred_at',
                'intakes' => 'taken_at',
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
            default => $query->latest('id'),
        };

        $limit = min(max((int) $request->get('limit', 20), 1), 100);
        $records = $query->limit($limit)->get();

        return Response::structured([
            'records' => $records->toArray(),
            'count' => $records->count(),
            'limit' => $limit,
        ]);
    }
}
