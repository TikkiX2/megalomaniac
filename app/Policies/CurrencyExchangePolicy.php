<?php

namespace App\Policies;

use App\Models\CurrencyExchange;
use App\Models\User;

class CurrencyExchangePolicy
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
    public function view(User $user, CurrencyExchange $currencyExchange): bool
    {
        return $user->id === $currencyExchange->user_id;
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
    public function update(User $user, CurrencyExchange $currencyExchange): bool
    {
        return $user->id === $currencyExchange->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CurrencyExchange $currencyExchange): bool
    {
        return $user->id === $currencyExchange->user_id;
    }
}
