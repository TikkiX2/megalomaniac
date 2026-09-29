<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;

class WorkoutPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }
}
