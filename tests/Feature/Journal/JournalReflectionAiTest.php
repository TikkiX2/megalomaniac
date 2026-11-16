<?php

use App\Models\AiProvider;
use App\Models\JournalDay;
use App\Models\JournalReflection;
use App\Models\User;
use App\Services\Journal\JournalReflectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function journalChatBody(string $content): array
{
    return [
        'model' => 'qa',
        'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ];
}

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('genera la reflexión con IA y guarda el modelo', function () {
    $this->user->update(['ai_enabled' => true]);
    AiProvider::factory()->for($this->user)->create([
        'name' => 'QA', 'url' => 'https://ai.example.com/v1', 'enabled' => true,
    ]);
    Http::fake(['ai.example.com/*' => Http::response(journalChatBody("Temas:\n- casa\n\nÁnimo: estable."), 200)]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    $reflection = JournalReflection::firstOrFail();

    expect($reflection->source)->toBe('ai')
        ->and($reflection->body)->toContain('Temas')
        ->and($reflection->model)->not->toBeNull();
});

it('cae al fallback si el proveedor falla', function () {
    $this->user->update(['ai_enabled' => true]);
    AiProvider::factory()->for($this->user)->create(['enabled' => true, 'url' => 'https://ai.example.com/v1']);
    Http::fake(['ai.example.com/*' => Http::response('boom', 500)]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    expect(JournalReflection::firstOrFail()->source)->toBe('fallback');
});

it('limita la generación a tres por minuto', function () {
    foreach (range(1, 3) as $i) {
        $this->post('/diario/reflexiones')->assertRedirect();
    }

    $this->post('/diario/reflexiones')->assertStatus(429);
});

it('repaso trae la reflexión guardada y el historial', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalReflection::factory()->for($this->user)->create(['week_start' => $week, 'body' => 'Resumen guardado']);

    $this->get('/diario/repaso')->assertInertia(fn ($page) => $page
        ->component('journal/Review')
        ->where('weekStart', $week->toDateString())
        ->where('reflection.body', 'Resumen guardado')
        ->has('reflections', 1));
});
