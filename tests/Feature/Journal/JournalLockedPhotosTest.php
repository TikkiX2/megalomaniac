<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('devuelve 423 para una foto privada bloqueada y 200 desbloqueada', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id, 'is_private' => true]);
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');
    $media = $moment->fresh()->getFirstMedia('photos');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertStatus(423);

    $this->post('/diario/desbloquear', ['password' => 'password']);
    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertOk();
});

it('una foto ajena sigue dando 404 aunque esté desbloqueado', function () {
    $this->post('/diario/desbloquear', ['password' => 'password']);
    $moment = JournalMoment::factory()->create();
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$moment->fresh()->getFirstMedia('photos')->id}")
        ->assertNotFound();
});

it('el timeline tapa el extracto y la foto de un día sólo privado', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'privado', 'is_private' => true]);

    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id,
        'journal_day_id' => $day->id,
        'occurred_at' => '2026-11-10 10:00:00',
        'body' => 'momento privado',
        'is_private' => true,
    ]);
    $moment->addMedia(UploadedFile::fake()->image('privada.jpg'))->toMediaCollection('photos', 'local');

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.excerpt', null)
        ->where('days.data.0.photo_url', null)
        ->where('days.data.0.locked', true));
});

it('el timeline muestra la foto privada después de desbloquear', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'privado', 'is_private' => true]);

    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id,
        'journal_day_id' => $day->id,
        'occurred_at' => '2026-11-10 10:00:00',
        'body' => 'momento privado',
        'is_private' => true,
    ]);
    $media = $moment->addMedia(UploadedFile::fake()->image('privada.jpg'))->toMediaCollection('photos', 'local');

    $this->post('/diario/desbloquear', ['password' => 'password']);

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.photo_url', route('journal.photos.show', [
            'moment' => $moment->id,
            'media' => $media->id,
        ]))
        ->where('days.data.0.locked', false));
});

it('el timeline muestra la foto de un momento público aunque el diario esté bloqueado', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-09', 'body' => 'público']);

    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id,
        'journal_day_id' => $day->id,
        'occurred_at' => '2026-11-09 10:00:00',
        'body' => 'momento público',
        'is_private' => false,
    ]);
    $media = $moment->addMedia(UploadedFile::fake()->image('publica.jpg'))->toMediaCollection('photos', 'local');

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.photo_url', route('journal.photos.show', [
            'moment' => $moment->id,
            'media' => $media->id,
        ]))
        ->where('days.data.0.locked', false));
});

it('el timeline muestra el extracto privado después de desbloquear', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'privado', 'is_private' => true]);

    $this->post('/diario/desbloquear', ['password' => 'password']);

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.excerpt', 'privado')
        ->where('days.data.0.locked', false));
});
