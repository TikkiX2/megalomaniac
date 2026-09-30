<?php

use App\Models\HealthProfessional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates, updates and deletes a professional', function () {
    $this->post('/health/professionals', [
        'type' => 'professional',
        'name' => 'Dra. López',
        'specialty' => 'Neurología',
    ])->assertRedirect(route('health.professionals.index'));

    $professional = HealthProfessional::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/professionals/{$professional->id}", ['is_active' => false])->assertRedirect();
    expect($professional->fresh()->is_active)->toBeFalse();

    $this->delete("/health/professionals/{$professional->id}")->assertRedirect();
    expect(HealthProfessional::find($professional->id))->toBeNull();
});

it('forbids editing another user professional', function () {
    $professional = HealthProfessional::factory()->create();

    $this->put("/health/professionals/{$professional->id}", ['name' => 'hack'])->assertForbidden();
});

it('lists only the authenticated user professionals when a foreign row matches the search', function () {
    HealthProfessional::factory()->create(['user_id' => $this->user->id, 'name' => 'Dra. López']);
    HealthProfessional::factory()->create(['name' => 'Dra. López']);

    $this->get('/health/professionals?search=lópez')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/professionals/Index')
            ->where('professionals.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Dra. López']));
});

it('filters professionals by type', function () {
    HealthProfessional::factory()->create(['user_id' => $this->user->id, 'name' => 'Dra. López', 'type' => 'professional']);
    HealthProfessional::factory()->create(['user_id' => $this->user->id, 'name' => 'Centro Médico Norte', 'type' => 'center']);

    $this->get('/health/professionals?type=center')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/professionals/Index')
            ->where('professionals.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Centro Médico Norte']));
});
