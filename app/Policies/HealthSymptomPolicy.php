<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthSymptom;
use App\Models\User;

class HealthSymptomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthSymptom $symptom): bool
    {
        return $user->id === $symptom->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthSymptom $symptom): bool
    {
        return $user->id === $symptom->user_id;
    }

    public function delete(User $user, HealthSymptom $symptom): bool
    {
        return $user->id === $symptom->user_id;
    }
}
