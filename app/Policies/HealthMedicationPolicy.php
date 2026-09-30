<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthMedication;
use App\Models\User;

class HealthMedicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthMedication $medication): bool
    {
        return $user->id === $medication->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthMedication $medication): bool
    {
        return $user->id === $medication->user_id;
    }

    public function delete(User $user, HealthMedication $medication): bool
    {
        return $user->id === $medication->user_id;
    }
}
