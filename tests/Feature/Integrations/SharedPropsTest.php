<?php

use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('shares the pending approvals count', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);
    ApprovalRequest::factory()->create(['user_id' => $user->id, 'connection_id' => $connection->id]);

    $this->actingAs($user)->get(route('integrations.approvals.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('approvals_pending_count', 1)
            ->has('flash'));
});

it('shares zero for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('approvals_pending_count', 0));
});
