<?php

namespace App\Policies;

use App\Models\ChatAttachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChatAttachmentPolicy
{
    public function view(User $user, ChatAttachment $attachment): Response
    {
        return $user->id === $attachment->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, ChatAttachment $attachment): bool
    {
        return $user->id === $attachment->user_id;
    }
}
