<?php

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;

class RoutinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }

    public function delete(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }
}
