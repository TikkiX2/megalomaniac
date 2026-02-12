<?php

namespace App\Policies;

use App\Models\SavingsReserve;
use App\Models\User;

class SavingsReservePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SavingsReserve $savingsReserve): bool
    {
        return $user->id === $savingsReserve->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SavingsReserve $savingsReserve): bool
    {
        return $user->id === $savingsReserve->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SavingsReserve $savingsReserve): bool
    {
        return $user->id === $savingsReserve->user_id;
    }
}
