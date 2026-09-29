<?php

namespace App\Ai\Tools;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GymQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s gym data: workouts with exercises and sets, the exercise library, and routines with targets. Use resource=exercises to discover exercise names/ids before adding exercises, and resource=routines to discover routine ids.';
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = $request['resource'] ?? 'workouts';

        return match ($resource) {
            'exercises' => $this->exercises($request),
            'routines' => $this->routines(),
            default => $this->workouts($request),
        };
    }

    private function workouts(Request $request): string
    {
        $query = Workout::with(['exercises.exercise', 'exercises.sets', 'routine'])
            ->where('user_id', $this->user->id);

        $query->where('started_at', '>=', now()->subDays((int) ($request['days'] ?? 30)));

        if (isset($request['exercise'])) {
            $query->whereHas('exercises.exercise', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request['exercise'].'%');
            });
        }

        $workouts = $query->latest('started_at')->limit(10)->get();

        if ($workouts->isEmpty()) {
            return 'No workouts found matching the criteria.';
        }

        return json_encode($workouts->toArray(), JSON_PRETTY_PRINT);
    }

    private function exercises(Request $request): string
    {
        $exercises = Exercise::query()
            ->when($request['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($request['muscle_group'] ?? null, fn ($query, $group) => $query->where('muscle_group', $group))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return json_encode($exercises->toArray(), JSON_PRETTY_PRINT);
    }

    private function routines(): string
    {
        $routines = Routine::with('exercises')
            ->where('user_id', $this->user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'name' => $routine->name,
                'focus' => $routine->focus,
                'scheduled_date' => $routine->scheduled_date,
                'status' => $routine->status,
                'exercises' => $routine->exercises->map(fn (Exercise $exercise) => [
                    'exercise_id' => $exercise->id,
                    'name' => $exercise->name,
                    'target_sets' => $exercise->pivot->target_sets,
                    'target_reps' => $exercise->pivot->target_reps,
                    'target_weight' => $exercise->pivot->target_weight,
                ])->all(),
            ]);

        return json_encode($routines->all(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()
                ->description('What to fetch: workouts (default), exercises (library), routines')
                ->enum(['workouts', 'exercises', 'routines']),
            'days' => $schema->integer()
                ->description('Days to look back for workouts (default: 30)')
                ->default(30),
            'exercise' => $schema->string()
                ->description('Filter workouts by exercise name (partial match)'),
            'search' => $schema->string()
                ->description('Filter the exercise library by name (resource=exercises)'),
            'muscle_group' => $schema->string()
                ->description('Filter the exercise library by muscle group (resource=exercises)'),
        ];
    }
}
