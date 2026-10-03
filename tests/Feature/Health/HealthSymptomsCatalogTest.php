<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Health\Enums\Severity;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(HealthService::class);
    $this->user = User::factory()->create();
});

it('creates and updates symptom catalogs', function () {
    $catalog = $this->service->createSymptomCatalog($this->user, [
        'name' => 'Migraña',
        'severity_default' => Severity::Severe->value,
    ]);

    expect($catalog->user_id)->toBe($this->user->id);
    expect($catalog->name)->toBe('Migraña');

    $updated = $this->service->updateSymptomCatalog($this->user, $catalog, ['name' => 'Cefalea', 'severity_default' => Severity::Mild->value]);
    expect($updated->name)->toBe('Cefalea');
});

it('creates and updates symptom episodes', function () {
    $episode = $this->service->createSymptomEpisode($this->user, [
        'started_at' => now()->subDays(5)->toDateTimeString(),
        'severity' => Severity::Moderate->value,
    ]);

    expect($episode->user_id)->toBe($this->user->id);
    expect($episode->started_at->format('Y-m-d'))->toBe(now()->subDays(5)->format('Y-m-d'));

    $updated = $this->service->updateSymptomEpisode($this->user, $episode, ['ended_at' => now()->toDateTimeString()]);
    expect($updated->ended_at->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
});
