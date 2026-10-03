<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Health\Enums\ConditionStatus;
use App\Health\Enums\MeasurementType;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthMedicationSchedule;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\HealthSymptomCatalog;
use App\Models\HealthSymptomEpisode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class HealthService
{
    public function findCondition(User $user, int $id): HealthCondition
    {
        return $this->owned(HealthCondition::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthCondition not found.');
    }

    public function findMedication(User $user, int $id): HealthMedication
    {
        return $this->owned(HealthMedication::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthMedication not found.');
    }

    public function createCondition(User $user, array $data): HealthCondition
    {
        return $user->healthConditions()->create($data);
    }

    public function updateCondition(User $user, HealthCondition $condition, array $data): HealthCondition
    {
        $this->assertOwner($user, $condition);
        $condition->update($data);

        return $condition->refresh();
    }

    public function deleteCondition(User $user, HealthCondition $condition): void
    {
        $this->assertOwner($user, $condition);
        $condition->delete();
    }

    public function createMedication(User $user, array $data): HealthMedication
    {
        return $user->healthMedications()->create($data);
    }

    public function updateMedication(User $user, HealthMedication $medication, array $data): HealthMedication
    {
        $this->assertOwner($user, $medication);
        $medication->update($data);

        return $medication->refresh();
    }

    public function deleteMedication(User $user, HealthMedication $medication): void
    {
        $this->assertOwner($user, $medication);
        $medication->delete();
    }

    public function findSchedule(User $user, int $id): HealthMedicationSchedule
    {
        return $this->owned(HealthMedicationSchedule::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthMedicationSchedule not found.');
    }

    public function createSchedule(User $user, array $data): HealthMedicationSchedule
    {
        return $user->healthMedicationSchedules()->create($data);
    }

    public function updateSchedule(User $user, HealthMedicationSchedule $schedule, array $data): HealthMedicationSchedule
    {
        $this->assertOwner($user, $schedule);
        $schedule->update($data);

        return $schedule->refresh();
    }

    public function deleteSchedule(User $user, HealthMedicationSchedule $schedule): void
    {
        $this->assertOwner($user, $schedule);
        $schedule->delete();
    }

    public function logIntake(User $user, HealthMedication $medication, array $data): HealthMedicationIntake
    {
        $this->assertOwner($user, $medication);

        return $medication->intakes()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function deleteIntake(User $user, HealthMedicationIntake $intake): void
    {
        $this->assertOwner($user, $intake);
        $intake->delete();
    }

    public function logMeasurement(User $user, array $data): HealthMeasurement
    {
        return DB::transaction(function () use ($user, $data): HealthMeasurement {
            $measurement = $user->healthMeasurements()->create($data);

            if ($this->shouldSyncProfileWeight($measurement)) {
                $this->syncProfileWeight($user);
            }

            HealthAlertService::checkForAlerts($user);

            return $measurement;
        });
    }

    public function updateMeasurement(User $user, HealthMeasurement $measurement, array $data): HealthMeasurement
    {
        $this->assertOwner($user, $measurement);

        return DB::transaction(function () use ($measurement, $data, $user): HealthMeasurement {
            $wasPersonalWeight = $this->shouldSyncProfileWeight($measurement);
            $measurement->update($data);

            if ($wasPersonalWeight || $this->shouldSyncProfileWeight($measurement)) {
                $this->syncProfileWeight($user);
            }

            return $measurement->refresh();
        });
    }

    public function deleteMeasurement(User $user, HealthMeasurement $measurement): void
    {
        $this->assertOwner($user, $measurement);

        DB::transaction(function () use ($measurement, $user): void {
            $wasPersonalWeight = $this->shouldSyncProfileWeight($measurement);
            $measurement->delete();

            if ($wasPersonalWeight) {
                $this->syncProfileWeight($user);
            }
        });
    }

    public function logSymptom(User $user, array $data): HealthSymptom
    {
        $symptom = $user->healthSymptoms()->create($data);

        HealthAlertService::checkForAlerts($user);

        return $symptom;
    }

    public function updateSymptom(User $user, HealthSymptom $symptom, array $data): HealthSymptom
    {
        $this->assertOwner($user, $symptom);
        $symptom->update($data);

        return $symptom->refresh();
    }

    public function deleteSymptom(User $user, HealthSymptom $symptom): void
    {
        $this->assertOwner($user, $symptom);
        $symptom->delete();
    }

    public function createProfessional(User $user, array $data): HealthProfessional
    {
        return $user->healthProfessionals()->create($data);
    }

    public function updateProfessional(User $user, HealthProfessional $professional, array $data): HealthProfessional
    {
        $this->assertOwner($user, $professional);
        $professional->update($data);

        return $professional->refresh();
    }

    public function deleteProfessional(User $user, HealthProfessional $professional): void
    {
        $this->assertOwner($user, $professional);
        $professional->delete();
    }

    public function createSymptomCatalog(User $user, array $data): HealthSymptomCatalog
    {
        return $user->healthSymptomCatalogs()->create($data);
    }

    public function findSymptomCatalog(User $user, int $id): HealthSymptomCatalog
    {
        return $this->owned(HealthSymptomCatalog::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthSymptomCatalog not found.');
    }

    public function updateSymptomCatalog(User $user, HealthSymptomCatalog $catalog, array $data): HealthSymptomCatalog
    {
        $this->assertOwner($user, $catalog);
        $catalog->update($data);

        return $catalog->refresh();
    }

    public function deleteSymptomCatalog(User $user, HealthSymptomCatalog $catalog): void
    {
        $this->assertOwner($user, $catalog);
        $catalog->delete();
    }

    public function createSymptomEpisode(User $user, array $data): HealthSymptomEpisode
    {
        return $user->healthSymptomEpisodes()->create($data);
    }

    public function findSymptomEpisode(User $user, int $id): HealthSymptomEpisode
    {
        return $this->owned(HealthSymptomEpisode::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthSymptomEpisode not found.');
    }

    public function updateSymptomEpisode(User $user, HealthSymptomEpisode $episode, array $data): HealthSymptomEpisode
    {
        $this->assertOwner($user, $episode);
        $episode->update($data);

        return $episode->refresh();
    }

    public function deleteSymptomEpisode(User $user, HealthSymptomEpisode $episode): void
    {
        $this->assertOwner($user, $episode);
        $episode->delete();
    }

    /**
     * Resumen del expediente para panel, MCP y chat.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        return [
            'active_conditions' => HealthCondition::where('user_id', $user->id)
                ->whereIn('status', [
                    ConditionStatus::Suspected->value,
                    ConditionStatus::Active->value,
                    ConditionStatus::InRemission->value,
                ])
                ->orderBy('name')
                ->get()
                ->toArray(),
            'active_medications' => HealthMedication::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->toArray(),
            'last_measurements' => HealthMeasurement::where('user_id', $user->id)
                ->latest('measured_at')
                ->limit(10)
                ->get()
                ->toArray(),
            'recent_symptoms' => HealthSymptom::where('user_id', $user->id)
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }

    private function shouldSyncProfileWeight(HealthMeasurement $measurement): bool
    {
        return $measurement->type === MeasurementType::Weight && $measurement->person_id === null;
    }

    private function syncProfileWeight(User $user): void
    {
        $latest = HealthMeasurement::query()
            ->where('user_id', $user->id)
            ->whereNull('person_id')
            ->where('type', MeasurementType::Weight->value)
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->value('value');

        $user->forceFill(['weight' => $latest])->saveQuietly();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function owned($query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    private function assertOwner(User $user, Model $model): void
    {
        if ((int) $model->user_id !== (int) $user->id) {
            throw new ModelNotFoundException(class_basename($model).' not found.');
        }
    }
}
