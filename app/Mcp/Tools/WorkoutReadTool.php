<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Workout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class WorkoutReadTool extends Tool
{
    protected string $name = 'workout-read';

    protected string $description = 'Read the authenticated user\'s workout history with optional filters for date range and limit.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Number of past days to include (default: 30)')->min(1)->max(365),
            'limit' => $schema->integer()->description('Maximum number of workouts to return (default: 20)')->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $days = (int) $request->get('days', 30);
        $limit = (int) $request->get('limit', 20);

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $workouts = Workout::where('user_id', $user->id)
            ->where('started_at', '>=', now()->subDays($days))
            ->with(['exercises.exercise', 'exercises.sets'])
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'routine_id' => $workout->routine_id,
                'started_at' => $workout->started_at?->toISOString(),
                'ended_at' => $workout->ended_at?->toISOString(),
                'notes' => $workout->notes,
                'exercises' => $workout->exercises->map(fn ($ex) => [
                    'id' => $ex->id,
                    'exercise_name' => $ex->exercise?->name,
                    'sets' => $ex->sets->map(fn ($set) => [
                        'set_number' => $set->set_number,
                        'weight' => $set->weight,
                        'reps' => $set->reps,
                        'rpe' => $set->rpe,
                        'completed' => $set->completed,
                    ])->all(),
                ])->all(),
            ]);

        return Response::structured([
            'workouts' => $workouts->all(),
            'count' => $workouts->count(),
            'days' => $days,
            'limit' => $limit,
        ]);
    }
}
