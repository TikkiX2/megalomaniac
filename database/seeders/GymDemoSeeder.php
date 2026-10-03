<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Seeder;

class GymDemoSeeder extends Seeder
{
    /**
     * Seed demo gym data (routines and finished workouts) for the canonical
     * test user created by DatabaseSeeder.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->firstOrFail();

        // Idempotency guard: skip entirely when the user already owns routines
        // and workouts (running the seeder twice must not duplicate anything).
        if (Routine::where('user_id', $user->id)->exists() && Workout::where('user_id', $user->id)->exists()) {
            $this->command->info('GymDemoSeeder skipped: routines and workouts already exist for test@example.com.');

            return;
        }

        $chest = Routine::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Pecho y Tríceps'],
        );

        $this->addRoutineExercises($chest, [
            ['name' => 'Press banca', 'target_sets' => 3, 'target_reps' => '8-12', 'target_weight' => '60'],
            ['name' => 'Aperturas con mancuernas', 'target_sets' => 3, 'target_reps' => '8-12', 'target_weight' => '12'],
            ['name' => 'Press francés', 'target_sets' => 3, 'target_reps' => '8-12', 'target_weight' => '25'],
        ]);

        $legs = Routine::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Pierna y Core'],
            ['scheduled_date' => now()->format('l')],
        );

        $this->addRoutineExercises($legs, [
            ['name' => 'Sentadilla', 'target_sets' => 3, 'target_reps' => '8-12', 'target_weight' => '80'],
            ['name' => 'Prensa de piernas', 'target_sets' => 3, 'target_reps' => '8-12', 'target_weight' => '140'],
            ['name' => 'Plancha abdominal', 'target_sets' => 3, 'target_reps' => '30-60s', 'target_weight' => null],
        ]);

        if (Workout::where('user_id', $user->id)->count() === 0) {
            $this->seedFinishedWorkout($user, $legs, 7, [
                ['name' => 'Sentadilla', 'sets' => [[60, 10, 8.0], [65, 8, 8.5], [70, 6, 9.0]]],
                ['name' => 'Prensa de piernas', 'sets' => [[130, 12, 8.0], [140, 10, 8.5]]],
                ['name' => 'Plancha abdominal', 'sets' => [[null, 45, 8.0], [null, 60, 9.0]]],
            ]);

            $this->seedFinishedWorkout($user, $chest, 3, [
                ['name' => 'Press banca', 'sets' => [[50, 10, 8.0], [55, 8, 8.5], [60, 6, 9.0]]],
                ['name' => 'Aperturas con mancuernas', 'sets' => [[12, 12, 8.0], [12.5, 10, 8.5]]],
                ['name' => 'Press francés', 'sets' => [[25, 10, 8.0], [27.5, 8, 8.5]]],
            ]);
        }

        $this->command->info('Gym demo data seeded for test@example.com (2 routines, 2 finished workouts).');
    }

    /**
     * Attach canonical seeder exercises to a routine with their target profile.
     * Skipped when the routine already has exercises (idempotent).
     *
     * @param  array<int, array{name: string, target_sets: int, target_reps: string, target_weight: string|null}>  $exercises
     */
    private function addRoutineExercises(Routine $routine, array $exercises): void
    {
        if ($routine->exercises()->count() > 0) {
            return;
        }

        $attach = [];

        foreach ($exercises as $order => $exercise) {
            $attach[Exercise::where('name', $exercise['name'])->firstOrFail()->id] = [
                'order' => $order + 1,
                'target_sets' => $exercise['target_sets'],
                'target_reps' => $exercise['target_reps'],
                'target_weight' => $exercise['target_weight'],
            ];
        }

        $routine->exercises()->attach($attach);
    }

    /**
     * Create a finished demo workout (same-day session, started 18:00 and ended
     * one hour later) from a routine with its exercises and completed sets at
     * different weights.
     *
     * @param  array<int, array{name: string, sets: array<int, array{0: float|null, 1: int, 2: float}>}>  $entries
     */
    private function seedFinishedWorkout(User $user, Routine $routine, int $daysAgo, array $entries): void
    {
        $startedAt = now()->subDays($daysAgo)->setTime(18, 0);
        $endedAt = $startedAt->copy()->addHour();

        $workout = $user->workouts()->create([
            'routine_id' => $routine->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);

        foreach ($entries as $order => $entry) {
            $workoutExercise = $workout->exercises()->create([
                'exercise_id' => Exercise::where('name', $entry['name'])->firstOrFail()->id,
                'order' => $order + 1,
            ]);

            foreach ($entry['sets'] as $setNumber => [$weight, $reps, $rpe]) {
                $workoutExercise->sets()->create([
                    'set_number' => $setNumber + 1,
                    'weight' => $weight,
                    'reps' => $reps,
                    'rpe' => $rpe,
                    'completed' => true,
                ]);
            }
        }
    }
}
