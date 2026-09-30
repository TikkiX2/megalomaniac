<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthMedicationIntake;
use App\Models\User;

class HealthMedicationIntakePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthMedicationIntake $intake): bool
    {
        return $user->id === $intake->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthMedicationIntake $intake): bool
    {
        return $user->id === $intake->user_id;
    }

    public function delete(User $user, HealthMedicationIntake $intake): bool
    {
        return $user->id === $intake->user_id;
    }
}
