<?php

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

it('sube una foto al disco local y la sirve al dueño', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('recuerdo.jpg', 800, 600),
    ])->assertRedirect();

    $media = $moment->fresh()->getFirstMedia('photos');

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('local');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertOk();
});

it('no sirve fotos de un momento ajeno', function () {
    $moment = JournalMoment::factory()->create();
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');
    $media = $moment->fresh()->getFirstMedia('photos');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertNotFound();
    $this->delete("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertNotFound();
});

it('rechaza archivos que no son imagen y más de 10 MB', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->create('doc.pdf', 10),
    ])->assertSessionHasErrors('file');

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('grande.jpg')->size(10241),
    ])->assertSessionHasErrors('file');
});

it('limita a 8 fotos por momento', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);

    foreach (range(1, 8) as $i) {
        $this->post("/diario/momentos/{$moment->id}/fotos", [
            'file' => UploadedFile::fake()->image("f{$i}.jpg"),
        ])->assertRedirect();
    }

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('nueve.jpg'),
    ])->assertSessionHasErrors('file');
});

it('borra la foto y el archivo', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');
    $media = $moment->fresh()->getFirstMedia('photos');

    $this->delete("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertRedirect();

    expect($moment->fresh()->getMedia('photos'))->toHaveCount(0);
});
