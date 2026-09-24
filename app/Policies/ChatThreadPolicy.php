<?php

namespace App\Policies;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChatThreadPolicy
{
    public function view(User $user, ChatThread $thread): Response
    {
        return $thread->belongsToUser($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, ChatThread $thread): bool
    {
        return $thread->belongsToUser($user);
    }

    public function delete(User $user, ChatThread $thread): bool
    {
        return $thread->belongsToUser($user);
    }
}
