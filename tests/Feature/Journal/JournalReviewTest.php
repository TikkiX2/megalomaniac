<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('repaso trae el mismo día de años anteriores y excluye privadas', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'occurred_at' => '2025-11-15 15:00:00', 'body' => 'Hace un año']);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'occurred_at' => '2024-11-15 15:00:00', 'body' => 'Hace dos', 'is_private' => true]);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'occurred_at' => '2025-10-01 15:00:00', 'body' => 'Otro día']);
    JournalDay::factory()->for($this->user)->create(['date' => '2024-11-15', 'body' => 'Entrada de hace dos años']);

    $this->get('/diario/repaso')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/Review')
            ->has('onThisDay', 2)
            ->where('onThisDay.0.excerpt', 'Hace un año')
            ->where('onThisDay.1.excerpt', 'Entrada de hace dos años'));
});

it('repaso no filtra memorias de otro usuario', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalMoment::factory()->create(['occurred_at' => '2025-11-15 15:00:00', 'body' => 'ajena']);

    $this->get('/diario/repaso')->assertOk()
        ->assertInertia(fn ($page) => $page->where('onThisDay', []));
});
