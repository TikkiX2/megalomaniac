<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('lista sólo días con contenido, más nuevos primero', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'viejo']);
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'nuevo']);
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-05', 'body' => null, 'tags' => ['solo-tag']]);

    $this->get('/diario/todas')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/All')
            ->has('days.data', 2)
            ->where('days.data.0.excerpt', 'nuevo')
            ->where('days.data.1.excerpt', 'viejo'));
});

it('usa el primer momento como extracto si el día no tiene texto', function () {
    JournalMoment::factory()->create([
        'user_id' => $this->user->id,
        'journal_day_id' => JournalDay::factory()->for($this->user)->create(['date' => '2026-11-09', 'body' => null])->id,
        'body' => 'Caminata por la costanera',
    ]);

    $this->get('/diario/todas')->assertOk()
        ->assertInertia(fn ($page) => $page->where('days.data.0.excerpt', 'Caminata por la costanera'));
});

it('no lista los días de otro usuario', function () {
    JournalDay::factory()->create(['date' => '2026-11-10', 'body' => 'ajena']);

    $this->get('/diario/todas')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/All')
            ->has('days.data', 0)
            ->where('total', 0));
});

it('recorta el extracto a 180 caracteres y toma la foto del primer momento con imagen', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-08', 'body' => null]);
    $texto = str_repeat('a', 200);

    $sinFoto = $day->moments()->create([
        'user_id' => $this->user->id,
        'occurred_at' => '2026-11-08 09:00:00',
        'body' => $texto,
        'is_private' => false,
    ]);

    $conFoto = $day->moments()->create([
        'user_id' => $this->user->id,
        'occurred_at' => '2026-11-08 10:00:00',
        'body' => 'Con foto',
        'is_private' => false,
    ]);

    $media = $conFoto->addMedia(UploadedFile::fake()->image('recuerdo.jpg'))
        ->toMediaCollection('photos', 'local');

    $this->get('/diario/todas')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/All')
            ->where('days.data.0.excerpt', mb_substr($texto, 0, 180).'...')
            ->where('days.data.0.moments_count', 2)
            ->where('days.data.0.photo_url', route('journal.photos.show', [
                'moment' => $conFoto->id,
                'media' => $media->id,
            ])));

    expect($sinFoto->fresh()->getMedia('photos'))->toHaveCount(0);
});
