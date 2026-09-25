<?php

use App\Integrations\ConnectorRegistry;
use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeConnector;

beforeEach(function () {
    FakeConnector::reset();
    $this->withoutVite();

    app()->instance(ConnectorRegistry::class, new ConnectorRegistry([FakeConnector::class]));
});

it('lists only the user pending approvals and history', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);

    ApprovalRequest::factory()->create(['user_id' => $user->id, 'connection_id' => $connection->id]);
    ApprovalRequest::factory()->create([
        'user_id' => $user->id,
        'connection_id' => $connection->id,
        'status' => ApprovalStatus::Executed,
    ]);
    ApprovalRequest::factory()->create();

    $this->actingAs($user)->get(route('integrations.approvals.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/approvals')
            ->has('pending', 1)
            ->has('history', 1)
            ->where('pending.0.connection_name', $connection->name));
});

it('approves an own approval', function () {
    Bus::fake();

    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'user_id' => $user->id,
        'connection_id' => $connection->id,
    ]);

    $this->actingAs($user)
        ->post(route('integrations.approvals.approve', $approval), ['note' => 'ok'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->fresh()->decided_by)->toBe($user->id);
});

it('rejects an own approval', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'user_id' => $user->id,
        'connection_id' => $connection->id,
    ]);

    $this->actingAs($user)
        ->post(route('integrations.approvals.reject', $approval), ['note' => 'no'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected);
});

it('404s foreign approvals', function () {
    $approval = ApprovalRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('integrations.approvals.approve', $approval))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->post(route('integrations.approvals.reject', $approval))
        ->assertNotFound();
});

it('validates the decision note length', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'user_id' => $user->id,
        'connection_id' => $connection->id,
    ]);

    $this->actingAs($user)
        ->post(route('integrations.approvals.approve', $approval), ['note' => str_repeat('x', 501)])
        ->assertSessionHasErrors('note');
});
