<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function freezeAt(string $utc): void
{
    Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
}

afterEach(function () {
    Carbon::setTestNow();
});

it('buckets late workouts in the user timezone', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 23:00 del 9/11 en Buenos Aires == 02:00 UTC del 10/11.
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 02:00:00']);
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-09 02:00:00']);
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-08 02:00:00']);

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('streak.current', 3)
            ->where('streak.days.5.date', '2026-11-09')
            ->where('streak.days.5.hasWorkout', true)
            ->where('weeklyVolumeByDay.5.date', '2026-11-09')
            ->where('streak.days.6.date', '2026-11-10')
            ->where('streak.days.6.hasWorkout', false));
});

it('buckets weekly volume in the user timezone', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 02:00 UTC del 10/11 == 23:00 local del 9/11: el volumen cae en el día 9.
    $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 02:00:00']);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $workoutExercise->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('weeklyVolumeByDay.5.date', '2026-11-09')
            ->where('weeklyVolumeByDay.5.volume', fn ($volume) => (float) $volume === 500.0)
            ->where('weeklyVolumeByDay.6.date', '2026-11-10')
            ->where('weeklyVolumeByDay.6.volume', fn ($volume) => (float) $volume === 0.0));
});

it('counts the full streak beyond seven days', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    for ($daysAgo = 0; $daysAgo < 10; $daysAgo++) {
        Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => Carbon::parse('2026-11-10 12:00:00', 'UTC')->subDays($daysAgo),
        ]);
    }

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('streak.current', 10));
});

it('keeps the streak alive when today has no workout yet', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 02:00:00']); // 9/11 local
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-09 02:00:00']); // 8/11 local

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('streak.current', 2));
});

it('breaks the streak after two days without training', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-08 02:00:00']); // 7/11 local

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('streak.current', 0));
});

it('counts a workout started after local midnight as today', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 03:30:00']); // 00:30 local del 10/11

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('streak.current', 1)
            ->where('streak.days.6.date', '2026-11-10'));
});
