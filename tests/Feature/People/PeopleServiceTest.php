<?php

use App\Models\Person;
use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\Models\User;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use App\Services\People\PeopleService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PeopleService::class);
    $this->user = User::factory()->create();
});

it('creates and updates a person scoped to the user', function () {
    $person = $this->service->createPerson($this->user, [
        'first_name' => 'Ana',
        'closeness' => Closeness::Close->value,
    ]);

    expect($person->user_id)->toBe($this->user->id);

    $updated = $this->service->updatePerson($this->user, $person, ['nickname' => 'Anita']);

    expect($updated->nickname)->toBe('Anita');
});

it('refuses to touch another user person', function () {
    $intruder = User::factory()->create();

    $this->expectException(ModelNotFoundException::class);
    $this->expectExceptionMessage('Person not found.');

    $this->service->findPerson($intruder, Person::factory()->create()->id);
});

it('logs an interaction and refreshes last_contacted_at', function () {
    $person = $this->service->createPerson($this->user, ['first_name' => 'Bruno']);
    $when = now()->subHours(2)->startOfMinute();

    $this->service->logInteraction($this->user, $person, [
        'channel' => InteractionChannel::Message->value,
        'occurred_at' => $when,
        'title' => 'Nos escribimos',
    ]);

    expect($person->fresh()->last_contacted_at->equalTo($when))->toBeTrue();
});

it('returns upcoming birthdays and key dates inside the window', function () {
    $person = $this->service->createPerson($this->user, [
        'first_name' => 'Cami',
        'birthday' => now()->addDays(3)->toDateString(),
    ]);
    $this->service->addKeyDate($this->user, $person, [
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => now()->addDays(40)->toDateString(),
        'is_recurring_annually' => false,
    ]);
    $this->service->addKeyDate($this->user, $person, [
        'type' => 'custom',
        'label' => 'Dentista',
        'date' => now()->addDays(10)->toDateString(),
        'is_recurring_annually' => false,
    ]);

    $upcoming = $this->service->upcoming($this->user, 30);

    expect($upcoming)->toHaveCount(2)
        ->and($upcoming->first()['kind'])->toBe('birthday')
        ->and($upcoming->first()['days_until'])->toBe(3)
        ->and($upcoming->last()['kind'])->toBe('key_date');
});

it('deletes key dates and socials with ownership checks', function () {
    $person = $this->service->createPerson($this->user, ['first_name' => 'Dani']);
    $keyDate = $this->service->addKeyDate($this->user, $person, [
        'type' => 'custom',
        'date' => now()->toDateString(),
    ]);
    $social = $this->service->addSocial($this->user, $person, [
        'network' => 'instagram',
        'handle' => '@dani',
    ]);

    $intruder = User::factory()->create();

    expect(fn () => $this->service->deleteSocial($intruder, $social))
        ->toThrow(ModelNotFoundException::class);

    expect(PersonSocial::find($social->id))->not->toBeNull();

    $this->service->deleteKeyDate($this->user, $keyDate);
    $this->service->deleteSocial($this->user, $social);

    expect(PersonKeyDate::find($keyDate->id))->toBeNull()
        ->and(PersonSocial::find($social->id))->toBeNull();
});

it('resolves the next occurrence of a recurring key date under immutable dates', function () {
    $person = $this->service->createPerson($this->user, ['first_name' => 'Eva']);
    $target = now()->addDays(5)->startOfDay();

    $keyDate = $this->service->addKeyDate($this->user, $person, [
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => $target->toDateString(),
        'is_recurring_annually' => true,
    ]);

    $next = $keyDate->nextOccurrence();

    expect($next)->toBeInstanceOf(CarbonInterface::class)
        ->and($next?->toDateString())->toBe($target->toDateString());
});
