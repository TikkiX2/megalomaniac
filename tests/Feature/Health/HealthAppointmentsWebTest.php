<?php

use App\Health\Enums\AppointmentStatus;
use App\Models\HealthAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates, updates and deletes an appointment', function () {
    $this->post('/health/appointments', [
        'title' => 'Chequeo anual',
        'scheduled_at' => '2026-10-10 10:00:00',
        'status' => AppointmentStatus::Scheduled->value,
    ])->assertRedirect(route('health.appointments.index'));

    $appointment = HealthAppointment::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/appointments/{$appointment->id}", [
        'title' => 'Chequeo anual actualizado',
        'status' => AppointmentStatus::Completed->value,
    ])->assertRedirect(route('health.appointments.index'));

    expect($appointment->fresh()->title)->toBe('Chequeo anual actualizado');
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);

    $this->delete("/health/appointments/{$appointment->id}")->assertRedirect(route('health.appointments.index'));
    expect(HealthAppointment::find($appointment->id))->toBeNull();
});

it('forbids editing another user appointment', function () {
    $other = HealthAppointment::factory()->create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Ajena',
        'scheduled_at' => now(),
        'status' => AppointmentStatus::Scheduled->value,
    ]);

    $this->put("/health/appointments/{$other->id}", ['title' => 'hack'])->assertForbidden();
    $this->delete("/health/appointments/{$other->id}")->assertForbidden();
});

it('scopes appointments to the authenticated user', function () {
    $own = HealthAppointment::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Mi cita',
        'scheduled_at' => now(),
        'status' => AppointmentStatus::Scheduled->value,
    ]);
    HealthAppointment::factory()->create(['title' => 'Otra', 'scheduled_at' => now(), 'status' => AppointmentStatus::Scheduled->value]);

    $this->get('/health/appointments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/appointments/Index')
            ->where('appointments.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$own->id]));
});

it('filters appointments by status and search', function () {
    HealthAppointment::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Revisión dental',
        'scheduled_at' => now(),
        'status' => AppointmentStatus::Scheduled->value,
    ]);
    HealthAppointment::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Consulta cardiológica',
        'scheduled_at' => now(),
        'status' => AppointmentStatus::Completed->value,
    ]);

    $this->get('/health/appointments?search=dental')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appointments.data', fn ($rows) => collect($rows)->pluck('title')->all() === ['Revisión dental']));

    $this->get('/health/appointments?status=completed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appointments.data', fn ($rows) => collect($rows)->pluck('title')->all() === ['Consulta cardiológica']));
});

it('renders the appointment create form', function () {
    $this->get('/health/appointments/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/appointments/Form')
            ->has('statusOptions')
            ->has('people')
            ->has('providers'));
});

it('renders the appointment edit form with authorization', function () {
    $appointment = HealthAppointment::factory()->create(['user_id' => $this->user->id]);

    $this->get("/health/appointments/{$appointment->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/appointments/Form')
            ->where('appointment.id', $appointment->id));
});
