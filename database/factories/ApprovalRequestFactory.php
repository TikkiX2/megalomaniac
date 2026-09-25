<?php

namespace Database\Factories;

use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'user_id' => User::factory(),
            'action_key' => 'github.issues.create',
            'params' => ['title' => fake()->sentence()],
            'access' => ActionAccess::Write,
            'summary' => fake()->sentence(),
            'rationale' => null,
            'status' => ApprovalStatus::Pending,
            'decided_at' => null,
            'decided_by' => null,
            'decision_note' => null,
            'executed_at' => null,
            'expires_at' => now()->addHours(72),
        ];
    }
}
