<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Health\Enums\IntakeStatus;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthMedicationSchedule;
use App\Models\User;
use App\Services\Health\HealthAdherenceService;
use App\Services\Health\HealthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthAdherenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_creation_and_adherence_calculation(): void
    {
        $user = User::factory()->create();
        $medication = HealthMedication::factory()->for($user)->create(['name' => 'Aspirin']);

        // Create schedule: every day at 08:00
        $schedule = HealthMedicationSchedule::factory()->for($user, 'user')->for($medication, 'medication')->create([
            'time' => '08:00:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        // Create 3 intakes within range
        $start = Carbon::parse('2026-09-28');
        $end = Carbon::parse('2026-10-02');

        HealthMedicationIntake::factory()->create([
            'user_id' => $user->id,
            'medication_id' => $medication->id,
            'taken_at' => Carbon::parse('2026-10-01 08:05:00'),
            'status' => IntakeStatus::Taken,
        ]);
        HealthMedicationIntake::factory()->create([
            'user_id' => $user->id,
            'medication_id' => $medication->id,
            'taken_at' => Carbon::parse('2026-10-02 08:10:00'),
            'status' => IntakeStatus::Taken,
        ]);
        // Missing 2026-09-28,29,30

        $service = new HealthAdherenceService;
        $adherence = $service->calculateAdherence($user, $medication, $start, $end);

        // Expected intakes: 5 days (Mon-Fri) => 5, actual 2 => 40%
        $this->assertEqualsWithDelta(40.0, $adherence, 0.01);
    }

    public function test_schedule_crud_via_service(): void
    {
        $user = User::factory()->create();
        $medication = HealthMedication::factory()->for($user)->create();

        $service = new HealthService;

        $schedule = $service->createSchedule($user, [
            'medication_id' => $medication->id,
            'time' => '09:00:00',
            'days_of_week' => [0, 6],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('health_medication_schedules', [
            'id' => $schedule->id,
            'user_id' => $user->id,
            'medication_id' => $medication->id,
        ]);

        $found = $service->findSchedule($user, $schedule->id);
        $this->assertEquals($schedule->id, $found->id);

        $updated = $service->updateSchedule($user, $schedule, ['is_active' => false]);
        $this->assertFalse($updated->is_active);

        $service->deleteSchedule($user, $schedule);
        $this->assertDatabaseMissing('health_medication_schedules', ['id' => $schedule->id]);
    }
}
