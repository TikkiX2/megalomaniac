<?php

use App\Models\JournalDay;
use App\Models\JournalReflection;
use App\Models\Person;
use App\Models\User;
use App\Services\Journal\JournalReflectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
});

it('guarda una reflexión por usuario y semana', function () {
    $service = app(JournalReflectionService::class);
    $week = $service->weekStart($this->user);

    $first = $service->generate($this->user, $week);
    $second = $service->generate($this->user, $week);

    expect(JournalReflection::count())->toBe(1)
        ->and($first->id)->toBe($second->id)
        ->and($second->source)->toBe('fallback');
});

it('el payload sólo incluye memorias públicas', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalDay::factory()->for($this->user)->create([
        'date' => $week->toDateString(), 'body' => 'público de la semana', 'is_private' => false,
    ]);
    JournalDay::factory()->for($this->user)->create([
        'date' => $week->addDay()->toDateString(), 'body' => 'SECRETO PRIVADO', 'is_private' => true,
    ]);

    $payload = app(JournalReflectionService::class)->publicPayload($this->user, $week);

    expect($payload['text'])->toContain('público de la semana')
        ->not->toContain('SECRETO PRIVADO');
});

it('deja fuera del payload y del fallback los momentos privados', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);

    JournalDay::factory()->for($this->user)->create([
        'date' => $week->toDateString(), 'body' => 'público de la semana',
    ]);

    $anchor = JournalDay::factory()->for($this->user)->create([
        'date' => $week->addDay()->toDateString(), 'body' => null, 'mood' => null, 'is_private' => true,
    ]);

    $person = Person::factory()->for($this->user)->create([
        'first_name' => 'Persona', 'last_name' => 'Secreta',
    ]);

    $moment = $anchor->moments()->create([
        'user_id' => $this->user->id,
        'title' => 'Título privado',
        'body' => 'SECRETO MOMENTO',
        'tags' => ['secretotag'],
        'occurred_at' => $week->toDateString().' 20:00:00',
        'is_private' => true,
    ]);

    $moment->people()->attach($person);

    $service = app(JournalReflectionService::class);
    $payload = $service->publicPayload($this->user, $week);
    $fallback = $service->fallback($this->user, $week);

    expect($payload['text'])->toContain('público de la semana')
        ->not->toContain('SECRETO MOMENTO')
        ->not->toContain('secretotag')
        ->not->toContain('Persona Secreta');

    expect($fallback)->toContain('público de la semana')
        ->not->toContain('SECRETO MOMENTO')
        ->not->toContain('secretotag')
        ->not->toContain('Persona Secreta');
});

it('incluye tags y personas de los momentos públicos', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);

    $day = JournalDay::factory()->for($this->user)->create([
        'date' => $week->toDateString(), 'body' => 'día con momento',
    ]);

    $person = Person::factory()->for($this->user)->create([
        'first_name' => 'Ana', 'last_name' => 'Pública',
    ]);

    $moment = $day->moments()->create([
        'user_id' => $this->user->id,
        'title' => 'Cena',
        'body' => 'Cena con Ana',
        'tags' => ['amistad'],
        'occurred_at' => $week->toDateString().' 21:00:00',
        'is_private' => false,
    ]);

    $moment->people()->attach($person);

    $payload = app(JournalReflectionService::class)->publicPayload($this->user, $week);

    expect($payload['text'])
        ->toContain('Cena con Ana')
        ->toContain('amistad')
        ->toContain('Ana Pública');
});

it('el fallback arma un resumen con datos de la semana', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalDay::factory()->for($this->user)->create([
        'date' => $week->toDateString(), 'body' => 'Día uno', 'mood' => 4, 'tags' => ['casa'],
    ]);

    $reflection = app(JournalReflectionService::class)->generate($this->user, $week);

    expect($reflection->body)->toContain('Día uno')
        ->and($reflection->body)->toContain('Sin IA');
});

it('una semana sin datos públicos devuelve null', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    expect(app(JournalReflectionService::class)->recap($this->user))->toBeNull();
});

it('calcula la semana desde el lunes local', function () {
    // 02:00 UTC del lunes 16 son las 23:00 del domingo 15 en Buenos Aires.
    Carbon::setTestNow('2026-11-16 02:00:00');

    expect(app(JournalReflectionService::class)->weekStart($this->user)->toDateString())
        ->toBe('2026-11-09');
});

it('rechaza una semana inválida, no lunes o fuera de la ventana', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    $service = app(JournalReflectionService::class);

    expect(fn () => $service->recap($this->user, '2026-11-17'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->recap($this->user, '2026-13-01'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->recap($this->user, '2026-01-05'))
        ->toThrow(InvalidArgumentException::class);
});

it('usa la reflexión guardada cuando existe', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    $reflection = JournalReflection::factory()->for($this->user)->create([
        'week_start' => '2026-11-16',
        'body' => 'resumen guardado',
        'source' => 'ai',
        'model' => 'test-model',
    ]);

    $recap = app(JournalReflectionService::class)->recap($this->user);
    $week = app(JournalReflectionService::class)->weekStart($this->user);

    expect($recap->id)->toBe($reflection->id)
        ->and($recap->body)->toBe('resumen guardado')
        ->and(app(JournalReflectionService::class)->forWeek($this->user, $week)->id)
        ->toBe($reflection->id);
});

it('cae al fallback aunque la IA esté habilitada y el stub devuelva null', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    $this->user->update(['ai_enabled' => true]);
    JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => 'con IA activa', 'mood' => 3,
    ]);

    $reflection = app(JournalReflectionService::class)->recap($this->user);

    expect($reflection->source)->toBe('fallback')
        ->and($reflection->model)->toBeNull()
        ->and($reflection->body)->toContain('con IA activa')
        ->and($reflection->generated_at)->not->toBeNull();
});

it('recorta los textos largos del payload y del resumen', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => str_repeat('a', 900),
    ]);

    $service = app(JournalReflectionService::class);
    $payload = $service->publicPayload($this->user, $service->weekStart($this->user));

    expect(mb_strlen($payload['text']))->toBeLessThanOrEqual(7000)
        ->and($payload['text'])->not->toContain(str_repeat('a', 501))
        ->and(mb_strlen($service->fallback($this->user, $service->weekStart($this->user))))
        ->toBeLessThanOrEqual(7000);
});
