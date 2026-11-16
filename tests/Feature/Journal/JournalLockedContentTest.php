<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('no expone el texto privado bloqueado', function () {
    $day = JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => 'secreto del día', 'is_private' => true,
    ]);
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'journal_day_id' => $day->id,
        'title' => 'título privado', 'body' => 'cuerpo privado', 'is_private' => true,
    ]);
    $moment->people()->attach(Person::factory()->for($this->user)->create()->id);
    $moment->addMedia(UploadedFile::fake()->image('privada.jpg'))
        ->toMediaCollection('photos', 'local');

    $this->get('/diario/2026-11-16')->assertOk()->assertInertia(fn ($page) => $page
        ->component('journal/Index')
        ->where('day.body', null)
        ->where('day.locked', true)
        ->where('moments.0.title', null)
        ->where('moments.0.body', null)
        ->where('moments.0.people', [])
        ->where('moments.0.photos', [])
        ->where('moments.0.locked', true));

    expect($moment->body)->toBe('cuerpo privado')
        ->and($moment->fresh()->people()->count())->toBe(1)
        ->and($moment->fresh()->getMedia('photos'))->toHaveCount(1);
});

it('muestra el texto privado desbloqueado', function () {
    JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => 'secreto del día', 'is_private' => true,
    ]);

    $this->post('/diario/desbloquear', ['password' => 'password']);

    $this->get('/diario/2026-11-16')->assertInertia(fn ($page) => $page
        ->where('day.body', 'secreto del día')
        ->where('day.locked', false));
});

it('no deja editar ni borrar un momento privado bloqueado', function () {
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'body' => 'privado', 'is_private' => true,
    ]);

    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'cambio'])
        ->assertSessionHasErrors('password');
    $this->delete("/diario/momentos/{$moment->id}")->assertSessionHasErrors('password');

    expect($moment->fresh()->body)->toBe('privado');
});

it('permite editar una memoria pública sin desbloquear', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id, 'body' => 'público']);

    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'editado'])->assertSessionHasNoErrors();

    expect($moment->fresh()->body)->toBe('editado');
});

it('no deja editar un día privado bloqueado', function () {
    $day = JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => 'secreto', 'is_private' => true,
    ]);

    $this->put('/diario/2026-11-16', ['body' => 'cambio'])
        ->assertSessionHasErrors('password');

    expect($day->fresh()->body)->toBe('secreto');
});

it('permite crear un día privado estando bloqueado', function () {
    $this->put('/diario/2026-11-16', ['body' => 'nuevo', 'is_private' => true])
        ->assertSessionHasNoErrors();

    expect(JournalDay::query()->where('date', '2026-11-16')->first()?->body)->toBe('nuevo');
});

it('permite editar contenido privado después de desbloquear', function () {
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'body' => 'privado', 'is_private' => true,
    ]);

    $this->post('/diario/desbloquear', ['password' => 'password']);
    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'cambio'])
        ->assertSessionHasNoErrors();

    expect($moment->fresh()->body)->toBe('cambio');
});

it('bloquea la foto de un momento privado mientras está bloqueado', function () {
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'is_private' => true,
    ]);
    $media = $moment->addMedia(UploadedFile::fake()->image('privada.jpg'))
        ->toMediaCollection('photos', 'local');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")
        ->assertStatus(423);

    $this->post('/diario/desbloquear', ['password' => 'password']);

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")
        ->assertOk();
});

it('no deja subir ni borrar fotos de un momento privado bloqueado', function () {
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'is_private' => true,
    ]);
    $media = $moment->addMedia(UploadedFile::fake()->image('privada.jpg'))
        ->toMediaCollection('photos', 'local');

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('nueva.jpg'),
    ])->assertSessionHasErrors('password');

    $this->delete("/diario/momentos/{$moment->id}/fotos/{$media->id}")
        ->assertSessionHasErrors('password');

    expect($moment->fresh()->getMedia('photos'))->toHaveCount(1);
});
