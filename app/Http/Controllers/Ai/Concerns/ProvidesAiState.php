<?php

namespace App\Http\Controllers\Ai\Concerns;

use App\Ai\Services\ChatService;
use App\Models\User;

/**
 * @property ChatService $service
 */
trait ProvidesAiState
{
    /**
     * @return array{enabled: bool, configured: bool, defaultModel: ?string, has_tavily_key: bool}
     */
    protected function aiState(User $user): array
    {
        return [
            'enabled' => (bool) $user->ai_enabled,
            'configured' => $this->service->isConfigured($user),
            'defaultModel' => $user->ai_model ?: null,
            'has_tavily_key' => filled($user->tavily_api_key),
        ];
    }
}
