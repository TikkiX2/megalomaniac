<?php

use App\Health\Enums\StudyType;
use App\Models\HealthStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates, updates and deletes a study', function () {
    $this->post('/health/studies', [
        'title' => 'Hemograma completo',
        'type' => StudyType::Lab->value,
        'performed_at' => '2026-09-01',
        'notes' => 'Con ayuno',
    ])->assertRedirect(route('health.studies.index'));

    $study = HealthStudy::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/studies/{$study->id}", [
        'title' => 'Hemograma actualizado',
    ])->assertRedirect(route('health.studies.index'));

    expect($study->fresh()->title)->toBe('Hemograma actualizado');

    $this->delete("/health/studies/{$study->id}")->assertRedirect(route('health.studies.index'));
    expect(HealthStudy::find($study->id))->toBeNull();
});

it('forbids editing another user study', function () {
    $study = HealthStudy::factory()->create();

    $this->put("/health/studies/{$study->id}", ['title' => 'hack'])->assertForbidden();

    expect($study->fresh()->title)->not->toBe('hack');
});

it('lists and filters studies of the authenticated user', function () {
    HealthStudy::factory()->create(['user_id' => $this->user->id, 'title' => 'Prueba Lab', 'type' => StudyType::Lab]);
    HealthStudy::factory()->create(['user_id' => $this->user->id, 'title' => 'Resonancia', 'type' => StudyType::Imaging]);
    HealthStudy::factory()->create(['title' => 'Ajena']);

    $this->get('/health/studies?search=lab')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/studies/Index')
            ->where('studies.data', fn ($rows) => collect($rows)->pluck('title')->all() === ['Prueba Lab']));

    $this->get('/health/studies?type=imaging')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('studies.data', fn ($rows) => collect($rows)->pluck('title')->all() === ['Resonancia']));
});

it('shows a study with relations', function () {
    $study = HealthStudy::factory()->create(['user_id' => $this->user->id]);

    $this->get("/health/studies/{$study->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/studies/Show')
            ->where('study.id', $study->id));
});

it('renders the study create form', function () {
    $this->get('/health/studies/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/studies/Form')
            ->has('typeOptions')
            ->has('people')
            ->has('providers')
            ->has('conditions'));
});
