<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Models\Workout;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Resource;

#[Name('workout-history')]
#[Description('Returns a summary of the authenticated user\'s workout history including totals and recent sessions.')]
#[MimeType('application/json')]
class WorkoutHistoryResource extends Resource
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $totalWorkouts = Workout::where('user_id', $user->id)->count();
        $workoutsThisWeek = Workout::where('user_id', $user->id)
            ->where('started_at', '>=', now()->startOfWeek())
            ->count();
        $workoutsThisMonth = Workout::where('user_id', $user->id)
            ->where('started_at', '>=', now()->startOfMonth())
            ->count();

        $recentWorkouts = Workout::where('user_id', $user->id)
            ->with(['exercises.exercise', 'exercises.sets'])
            ->latest('started_at')
            ->limit(5)
            ->get()
            ->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'started_at' => $workout->started_at?->toISOString(),
                'ended_at' => $workout->ended_at?->toISOString(),
                'notes' => $workout->notes,
                'exercise_count' => $workout->exercises->count(),
                'total_sets' => $workout->exercises->sum(fn ($ex) => $ex->sets->count()),
            ]);

        return Response::json([
            'total_workouts' => $totalWorkouts,
            'workouts_this_week' => $workoutsThisWeek,
            'workouts_this_month' => $workoutsThisMonth,
            'recent_workouts' => $recentWorkouts->all(),
        ]);
    }
}
