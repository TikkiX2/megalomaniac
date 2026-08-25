<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WorkoutQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s workout history, exercises, and routines. Use this to answer questions about workouts, progress, PRs, and exercise performance.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $this->user->id);

        if (isset($request['days'])) {
            $query->where('started_at', '>=', now()->subDays($request['days']));
        }

        if (isset($request['exercise'])) {
            $query->whereHas('exercises', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request['exercise'].'%');
            });
        }

        $workouts = $query->latest('started_at')->limit(10)->get();

        if ($workouts->isEmpty()) {
            return 'No workouts found matching the criteria.';
        }

        return json_encode($workouts->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('Number of days to look back (default: 30)')
                ->default(30),
            'exercise' => $schema->string()
                ->description('Filter by exercise name (partial match)'),
        ];
    }
}
