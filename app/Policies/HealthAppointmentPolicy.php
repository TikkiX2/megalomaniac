<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthAppointment;
use App\Models\User;

class HealthAppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthAppointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthAppointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }

    public function delete(User $user, HealthAppointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }
}
