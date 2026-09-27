<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MemoryPolicy
{
    public function view(User $user, Memory $memory): Response
    {
        return $memory->user_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Memory $memory): bool
    {
        return $memory->user_id === $user->getKey();
    }

    public function delete(User $user, Memory $memory): bool
    {
        return $memory->user_id === $user->getKey();
    }
}
