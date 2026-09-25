<?php

use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

it('lists activity scoped to the user with filters', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create();

    IntegrationActionLog::factory()->create([
        'user_id' => $user->id,
        'connection_id' => $connection->id,
        'status' => 'success',
    ]);
    IntegrationActionLog::factory()->create(['status' => 'failed']);

    $this->actingAs($user)->get(route('integrations.activity.index', ['status' => 'success']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/activity')
            ->has('logs.data', 1)
            ->where('logs.data.0.status', 'success')
            ->has('filters'));
});

it('filters activity by connection', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create();
    $other = Connection::factory()->for($user)->create();

    IntegrationActionLog::factory()->create(['user_id' => $user->id, 'connection_id' => $connection->id]);
    IntegrationActionLog::factory()->create(['user_id' => $user->id, 'connection_id' => $other->id]);

    $this->actingAs($user)->get(route('integrations.activity.index', ['connection_id' => $connection->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 1));
});

it('does not leak foreign activity', function () {
    IntegrationActionLog::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('integrations.activity.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 0));
});
