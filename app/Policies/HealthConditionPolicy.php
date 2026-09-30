<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthCondition;
use App\Models\User;

class HealthConditionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthCondition $condition): bool
    {
        return $user->id === $condition->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthCondition $condition): bool
    {
        return $user->id === $condition->user_id;
    }

    public function delete(User $user, HealthCondition $condition): bool
    {
        return $user->id === $condition->user_id;
    }
}
