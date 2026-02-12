<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }
}
