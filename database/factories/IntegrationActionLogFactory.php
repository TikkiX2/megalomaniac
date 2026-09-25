<?php

namespace Database\Factories;

use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationActionLog>
 */
class IntegrationActionLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'user_id' => fn (array $attributes) => Connection::find($attributes['connection_id'])->user_id,
            'approval_request_id' => null,
            'action_key' => 'github.issues.list',
            'access' => ActionAccess::Read,
            'actor_type' => null,
            'actor_id' => null,
            'source' => 'ui',
            'params' => [],
            'result_summary' => 'ok',
            'status' => 'success',
            'error' => null,
            'duration_ms' => 12,
        ];
    }
}
