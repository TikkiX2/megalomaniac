<?php

namespace App\Console\Commands;

use App\Health\Enums\IntakeStatus;
use App\Models\HealthMedicationIntake;
use App\Models\HealthMedicationSchedule;
use Illuminate\Console\Command;

class CheckMedicationAdherence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:adherence:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check medication adherence and dispatch reminders for missing intakes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now()->toDateString();
        $dayOfWeek = now()->dayOfWeek;
        $schedules = HealthMedicationSchedule::query()
            ->where('is_active', true)
            ->whereJsonContains('days_of_week', $dayOfWeek)
            ->with(['medication', 'user'])
            ->get();

        $missing = 0;

        foreach ($schedules as $schedule) {
            $hasIntake = HealthMedicationIntake::query()
                ->where('user_id', $schedule->user_id)
                ->where('medication_id', $schedule->medication_id)
                ->whereDate('taken_at', $today)
                ->where('status', IntakeStatus::Taken->value)
                ->exists();

            if (! $hasIntake) {
                // Stub: dispatch notification (placeholder)
                $this->info("Missing intake for user {$schedule->user_id} medication {$schedule->medication_id} at {$schedule->time}");
                $missing++;
            }
        }

        $this->info("Checked {$schedules->count()} schedules. Missing intakes: {$missing}.");

        return self::SUCCESS;
    }
}
