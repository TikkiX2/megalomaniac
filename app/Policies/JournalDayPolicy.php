<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JournalDay;
use App\Models\User;

class JournalDayPolicy
{
    public function view(User $user, JournalDay $day): bool
    {
        return $user->id === $day->user_id;
    }

    public function update(User $user, JournalDay $day): bool
    {
        return $user->id === $day->user_id;
    }

    public function delete(User $user, JournalDay $day): bool
    {
        return $user->id === $day->user_id;
    }
}
