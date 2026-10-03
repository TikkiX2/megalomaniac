<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthStudy;
use App\Models\User;

class HealthStudyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthStudy $study): bool
    {
        return $user->id === $study->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthStudy $study): bool
    {
        return $user->id === $study->user_id;
    }

    public function delete(User $user, HealthStudy $study): bool
    {
        return $user->id === $study->user_id;
    }
}
