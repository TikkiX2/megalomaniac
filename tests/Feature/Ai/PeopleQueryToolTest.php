<?php

use App\Ai\Tools\PeopleQueryTool;
use App\Models\Person;
use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function peopleQueryTool(User $user): PeopleQueryTool
{
    return new PeopleQueryTool($user, app(PeopleService::class));
}

it('finds people by search and returns the detail with key dates', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Ana',
        'birthday' => now()->addDays(5)->toDateString(),
    ]);
    $person->keyDates()->create([
        'user_id' => $user->id,
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => now()->subYears(2)->toDateString(),
        'is_recurring_annually' => true,
    ]);
    Person::factory()->create(['first_name' => 'Bruno']);

    $list = json_decode(peopleQueryTool($user)->handle(new Request(['search' => 'ana'])), true);

    expect($list['records'])->toHaveCount(1)
        ->and($list['records'][0]['first_name'])->toBe('Ana');

    $detail = json_decode(peopleQueryTool($user)->handle(new Request(['person_id' => $person->id])), true);

    expect($detail['person']['first_name'])->toBe('Ana')
        ->and($detail['person']['key_dates'])->toHaveCount(1)
        ->and($detail['person']['upcoming'])->not->toBeEmpty();
});

it('excludes archived people from the list', function () {
    $user = User::factory()->create();
    Person::factory()->create(['user_id' => $user->id, 'first_name' => 'Ana']);
    Person::factory()->create(['user_id' => $user->id, 'first_name' => 'Zoe', 'is_archived' => true]);

    $list = json_decode(peopleQueryTool($user)->handle(new Request([])), true);

    expect($list['records'])->toHaveCount(1)
        ->and($list['records'][0]['first_name'])->toBe('Ana');
});

it('lists stale contacts and upcoming dates', function () {
    $user = User::factory()->create();
    Person::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Ana',
        'birthday' => null,
        'last_contacted_at' => now()->subDays(60),
    ]);
    Person::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Cami',
        'birthday' => now()->addDays(3)->toDateString(),
        'last_contacted_at' => now()->subDay(),
    ]);

    $stale = json_decode(peopleQueryTool($user)->handle(new Request(['stale_days' => 30])), true);
    expect($stale['records'])->toHaveCount(1)
        ->and($stale['records'][0]['first_name'])->toBe('Ana');

    $upcoming = json_decode(peopleQueryTool($user)->handle(new Request(['upcoming_days' => 7])), true);
    expect($upcoming['upcoming'])->toHaveCount(1)
        ->and($upcoming['upcoming'][0]['kind'])->toBe('birthday');
});
