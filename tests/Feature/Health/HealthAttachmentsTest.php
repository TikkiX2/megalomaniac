<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Models\HealthAppointment;
use App\Models\HealthStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('uploads an attachment to a study and lists it on the show page', function () {
    $study = HealthStudy::factory()->create(['user_id' => $this->user->id]);

    $this->post("/health/studies/{$study->id}/attachments", [
        'file' => UploadedFile::fake()->create('informe.pdf', 100),
    ])->assertRedirect();

    expect($study->fresh()->getMedia('attachments'))->toHaveCount(1)
        ->and($study->fresh()->getFirstMedia('attachments')->file_name)->toBe('informe.pdf');

    $this->get("/health/studies/{$study->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/studies/Show')
            ->has('study.media', 1));
});

it('uploads multiple attachments when creating a study with forceFormData', function () {
    $this->post('/health/studies', [
        'title' => 'Laboratorio completo',
        'type' => 'lab',
        'performed_at' => '2026-09-25',
        'attachments' => [
            UploadedFile::fake()->create('resultados.pdf', 100),
            UploadedFile::fake()->create('nota.jpg', 50),
        ],
    ])->assertRedirect(route('health.studies.index'));

    $study = HealthStudy::where('title', 'Laboratorio completo')->firstOrFail();

    expect($study->getMedia('attachments'))->toHaveCount(2);
});

it('uploads an attachment to an appointment and shows it', function () {
    $appointment = HealthAppointment::factory()->create(['user_id' => $this->user->id]);

    $this->post("/health/appointments/{$appointment->id}/attachments", [
        'file' => UploadedFile::fake()->create('orden.pdf', 100),
    ])->assertRedirect();

    expect($appointment->fresh()->getMedia('attachments'))->toHaveCount(1);

    $this->get("/health/appointments/{$appointment->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/appointments/Show')
            ->has('appointment.media', 1));
});

it('forbids uploading attachments to another user records', function () {
    $foreignStudy = HealthStudy::factory()->create();
    $foreignAppointment = HealthAppointment::factory()->create();

    $this->post("/health/studies/{$foreignStudy->id}/attachments", [
        'file' => UploadedFile::fake()->create('x.pdf', 10),
    ])->assertForbidden();

    $this->post("/health/appointments/{$foreignAppointment->id}/attachments", [
        'file' => UploadedFile::fake()->create('x.pdf', 10),
    ])->assertForbidden();
});

it('deletes an attachment owned by the authenticated user', function () {
    $study = HealthStudy::factory()->create(['user_id' => $this->user->id]);
    $media = $study->addMedia(UploadedFile::fake()->create('informe.pdf', 100))
        ->toMediaCollection('attachments');

    $this->delete("/health/studies/{$study->id}/attachments/{$media->id}")
        ->assertRedirect();

    expect($study->fresh()->getMedia('attachments'))->toHaveCount(0);
});

it('validates that the attachment is a file', function () {
    $study = HealthStudy::factory()->create(['user_id' => $this->user->id]);

    $this->post("/health/studies/{$study->id}/attachments", ['file' => 'no-file'])
        ->assertSessionHasErrors('file');
});
