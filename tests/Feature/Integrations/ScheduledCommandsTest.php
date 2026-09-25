<?php

use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\IntegrationActionLog;

it('expires pending approvals past ttl', function () {
    $stale = ApprovalRequest::factory()->create(['expires_at' => now()->subHour()]);
    $fresh = ApprovalRequest::factory()->create(['expires_at' => now()->addHour()]);

    $this->artisan('integrations:expire-approvals')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(ApprovalStatus::Expired)
        ->and($fresh->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('prunes old activity logs', function () {
    $old = IntegrationActionLog::factory()->create(['created_at' => now()->subDays(120)]);
    $new = IntegrationActionLog::factory()->create();

    $this->artisan('integrations:prune-activity')->assertSuccessful();

    expect(IntegrationActionLog::find($old->id))->toBeNull()
        ->and(IntegrationActionLog::find($new->id))->not->toBeNull();
});
