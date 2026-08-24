<?php

namespace App\Policies;

use App\Models\PurchaseCategory;
use App\Models\User;

class PurchaseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseCategory $purchaseCategory): bool
    {
        return $user->id === $purchaseCategory->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PurchaseCategory $purchaseCategory): bool
    {
        return $user->id === $purchaseCategory->user_id;
    }

    public function delete(User $user, PurchaseCategory $purchaseCategory): bool
    {
        return $user->id === $purchaseCategory->user_id;
    }
}
