<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Person $person): bool
    {
        return $user->id === $person->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Person $person): bool
    {
        return $user->id === $person->user_id;
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->id === $person->user_id;
    }
}
