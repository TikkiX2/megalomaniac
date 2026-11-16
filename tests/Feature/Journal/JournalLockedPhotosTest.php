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

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.excerpt', null)
        ->where('days.data.0.photo_url', null)
        ->where('days.data.0.locked', true));
});

it('el timeline muestra el extracto privado después de desbloquear', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'privado', 'is_private' => true]);

    $this->post('/diario/desbloquear', ['password' => 'password']);

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.excerpt', 'privado')
        ->where('days.data.0.locked', false));
});
