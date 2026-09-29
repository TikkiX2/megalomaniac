<?php

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates people with enum casts and defaults', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create(['user_id' => $user->id]);

    expect($person->fresh()->closeness)->toBeInstanceOf(Closeness::class)
        ->and($person->fresh()->is_archived)->toBeFalse()
        ->and($person->fresh()->last_contacted_at)->toBeNull();
});

it('updates last_contacted_at when an interaction is logged', function () {
    $person = Person::factory()->create();
    $when = now()->subDays(3)->startOfMinute();

    PersonInteraction::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'channel' => InteractionChannel::Call,
        'occurred_at' => $when,
    ]);

    expect($person->fresh()->last_contacted_at->equalTo($when))->toBeTrue();
});

it('recomputes last_contacted_at when the latest interaction is deleted', function () {
    $person = Person::factory()->create();
    $old = PersonInteraction::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'occurred_at' => now()->subDays(10),
    ]);
    $recent = PersonInteraction::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'occurred_at' => now()->subDay(),
    ]);

    $recent->delete();

    expect($person->fresh()->last_contacted_at->equalTo($old->occurred_at))->toBeTrue();
});

it('scopes visible people', function () {
    Person::factory()->create(['is_archived' => false]);
    Person::factory()->create(['is_archived' => true]);

    expect(Person::visible()->count())->toBe(1);
});
