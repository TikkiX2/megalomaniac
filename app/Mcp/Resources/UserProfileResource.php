<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Resource;

#[Name('user-profile')]
#[Description('Returns the authenticated user\'s profile information.')]
#[MimeType('application/json')]
class UserProfileResource extends Resource
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        return Response::json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'weight' => $user->weight,
            'height' => $user->height,
            'target_weight' => $user->target_weight,
            'ai_enabled' => $user->ai_enabled,
            'created_at' => $user->created_at?->toISOString(),
        ]);
    }
}
