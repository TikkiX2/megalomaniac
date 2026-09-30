<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\PeopleLogTool;
use App\Mcp\Tools\PeopleReadTool;
use App\Mcp\Tools\PeopleWriteTool;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\InteractionChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, reads, updates and deletes people', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, ['action' => 'create_person', 'first_name' => 'Ana'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('person.first_name', 'Ana')
            ->etc());

    $person = Person::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('records')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, [
            'action' => 'update_person',
            'person_id' => $person->id,
            'nickname' => 'Anita',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('person.nickname', 'Anita')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, ['action' => 'delete_person', 'person_id' => $person->id])
        ->assertOk();

    expect(Person::find($person->id))->toBeNull();
});

it('logs an interaction through the log tool and refreshes last contact', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create(['user_id' => $user->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleLogTool::class, [
            'person_id' => $person->id,
            'channel' => InteractionChannel::Call->value,
            'title' => 'Llamada',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('interaction.person_id', $person->id)
            ->etc());

    expect(PersonInteraction::where('person_id', $person->id)->count())->toBe(1)
        ->and($person->fresh()->last_contacted_at)->not->toBeNull();
});

it('returns a validation error instead of throwing on invalid update values', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create(['user_id' => $user->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, [
            'action' => 'update_person',
            'person_id' => $person->id,
            'closeness' => 'buddy',
        ])
        ->assertHasErrors(['closeness']);

    expect($person->fresh()->closeness->value)->toBe('friend');
});

it('returns upcoming key dates and scopes tools to the authenticated user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $person = Person::factory()->create([
        'user_id' => $owner->id,
        'birthday' => now()->addDays(4)->toDateString(),
    ]);

    MegalomaniacServer::actingAs($owner)
        ->tool(PeopleReadTool::class, ['upcoming_days' => 30])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('upcoming')
            ->etc());

    MegalomaniacServer::actingAs($intruder)
        ->tool(PeopleWriteTool::class, ['action' => 'update_person', 'person_id' => $person->id, 'nickname' => 'hack'])
        ->assertHasErrors(['not found']);

    expect($person->fresh()->nickname)->not->toBe('hack');
});
