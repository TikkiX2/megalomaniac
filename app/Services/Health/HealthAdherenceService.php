<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Health\Enums\IntakeStatus;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthMedicationSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class HealthAdherenceService
{
    /**
     * Calculate adherence percentage for a medication over a date range.
     */
    public function calculateAdherence(User $user, HealthMedication $medication, Carbon $start, Carbon $end): float
    {
        $this->assertOwner($user, $medication);

        $schedules = HealthMedicationSchedule::query()
            ->where('user_id', $user->id)
            ->where('medication_id', $medication->id)
            ->where('is_active', true)
            ->get();

        if ($schedules->isEmpty()) {
            return 0.0;
        }

        $expected = $this->countExpectedIntakes($schedules, $start, $end);
        $actual = $this->countActualIntakes($user, $medication, $start, $end);

        if ($expected === 0) {
            return 0.0;
        }

        return round(($actual / $expected) * 100, 2);
    }

    private function countExpectedIntakes($schedules, Carbon $start, Carbon $end): int
    {
        $expected = 0;
        $period = $start->copy()->startOfDay()->daysUntil($end->copy()->addDay());

        foreach ($period as $date) {
            /** @var \DateTime $date */
            $carbonDate = Carbon::instance($date);
            $dayOfWeek = $carbonDate->dayOfWeek; // 0 = Sunday

            foreach ($schedules as $schedule) {
                $days = $schedule->days_of_week ?? [];

                if (empty($days)) {
                    $expected++;

                    continue;
                }

                if (in_array($dayOfWeek, $days, true)) {
                    $expected++;
                }
            }
        }

        return $expected;
    }

    private function countActualIntakes(User $user, HealthMedication $medication, Carbon $start, Carbon $end): int
    {
        return HealthMedicationIntake::query()
            ->where('user_id', $user->id)
            ->where('medication_id', $medication->id)
            ->whereBetween('taken_at', [$start->startOfDay(), $end->endOfDay()])
            ->where('status', IntakeStatus::Taken->value)
            ->count();
    }

    private function assertOwner(User $user, HealthMedication $medication): void
    {
        if ((int) $medication->user_id !== (int) $user->id) {
            throw new AuthorizationException('Medication not found.');
        }
    }
}
