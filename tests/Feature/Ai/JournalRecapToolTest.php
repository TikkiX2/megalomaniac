<?php

use App\Ai\Enums\AiScope;
use App\Ai\Tools\JournalRecapTool;
use App\Ai\Tools\ToolCatalog;
use App\Models\JournalDay;
use App\Models\JournalReflection;
use App\Models\User;
use App\Services\Journal\JournalReflectionService;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
});

it('devuelve la reflexión guardada de la semana', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalReflection::factory()->for($this->user)->create([
        'week_start' => $week, 'body' => 'Resumen de la semana',
    ]);

    $tool = new JournalRecapTool($this->user, app(JournalReflectionService::class));
    $result = (string) $tool->handle(new Request(['week_start' => $week->toDateString()]));

    expect($result)->toContain('Resumen de la semana');
});

it('genera la semana si no había resumen', function () {
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día']);

    $tool = new JournalRecapTool($this->user, app(JournalReflectionService::class));
    $result = (string) $tool->handle(new Request([]));

    expect(JournalReflection::count())->toBe(1)
        ->and($result)->toContain('Sin IA');
});

it('responde con un mensaje si la semana no es lunes', function () {
    $tool = new JournalRecapTool($this->user, app(JournalReflectionService::class));

    $result = (string) $tool->handle(new Request(['week_start' => '2026-11-17']));

    expect($result)->toContain('lunes');
});

it('el grupo journal está en el catálogo y en las keywords', function () {
    expect(ToolCatalog::isValidGroup('journal'))->toBeTrue()
        ->and(config('ai_tools.keywords.journal'))->toContain('diario')
        ->and(AiScope::fromModuleKey('journal'))->toBe(AiScope::ModuleJournal);
});
