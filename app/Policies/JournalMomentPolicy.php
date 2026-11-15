<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JournalMoment;
use App\Models\User;

class JournalMomentPolicy
{
    public function view(User $user, JournalMoment $moment): bool
    {
        return $user->id === $moment->user_id;
    }

    public function update(User $user, JournalMoment $moment): bool
    {
        return $user->id === $moment->user_id;
    }

    public function delete(User $user, JournalMoment $moment): bool
    {
        return $user->id === $moment->user_id;
    }
}
