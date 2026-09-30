<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthProfessional;
use App\Models\User;

class HealthProfessionalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthProfessional $professional): bool
    {
        return $user->id === $professional->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthProfessional $professional): bool
    {
        return $user->id === $professional->user_id;
    }

    public function delete(User $user, HealthProfessional $professional): bool
    {
        return $user->id === $professional->user_id;
    }
}
