<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthMeasurement;
use App\Models\User;

class HealthMeasurementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthMeasurement $measurement): bool
    {
        return $user->id === $measurement->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthMeasurement $measurement): bool
    {
        return $user->id === $measurement->user_id;
    }

    public function delete(User $user, HealthMeasurement $measurement): bool
    {
        return $user->id === $measurement->user_id;
    }
}
