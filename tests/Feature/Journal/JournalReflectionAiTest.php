<?php

use App\Models\AiProvider;
use App\Models\JournalDay;
use App\Models\JournalReflection;
use App\Models\User;
use App\Services\Journal\JournalReflectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
    Carbon::setTestNow('2026-11-16 10:00:00');
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

it('guarda el fallback ante un error no recuperable del proveedor', function () {
    $this->user->update(['ai_enabled' => true]);
    AiProvider::factory()->for($this->user)->create(['enabled' => true, 'url' => 'https://ai.example.com/v1']);
    Http::fake(['ai.example.com/*' => Http::response('not found', 404)]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    $reflection = JournalReflection::firstOrFail();

    expect($reflection->source)->toBe('fallback')
        ->and($reflection->body)->toContain('Sin IA');
});

it('cae al fallback si la IA devuelve una respuesta vacía', function () {
    $this->user->update(['ai_enabled' => true]);
    AiProvider::factory()->for($this->user)->create(['enabled' => true, 'url' => 'https://ai.example.com/v1']);
    Http::fake(['ai.example.com/*' => Http::response(journalChatBody("   \n  "), 200)]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    $reflection = JournalReflection::firstOrFail();

    expect($reflection->source)->toBe('fallback')
        ->and($reflection->model)->toBeNull()
        ->and($reflection->body)->toContain('Sin IA');
});

it('usa el fallback sin IA habilitada y sin proveedores', function () {
    Http::preventStrayRequests();

    $this->user->update(['ai_enabled' => false]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    expect(JournalReflection::firstOrFail()->source)->toBe('fallback');
});

it('rechaza una semana que no empieza un lunes', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);

    $this->post('/diario/reflexiones', ['week_start' => $week->addDay()->toDateString()])
        ->assertSessionHasErrors('week_start');

    expect(JournalReflection::count())->toBe(0);
});

it('rechaza una semana fuera de las últimas ocho', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);

    foreach ([$week->subWeeks(8), $week->addWeek()] as $invalid) {
        $this->post('/diario/reflexiones', ['week_start' => $invalid->toDateString()])
            ->assertSessionHasErrors('week_start');
    }

    expect(JournalReflection::count())->toBe(0);
});

it('rechaza una semana con formato inválido', function () {
    $this->post('/diario/reflexiones', ['week_start' => '16/11/2026'])
        ->assertSessionHasErrors('week_start');

    expect(JournalReflection::count())->toBe(0);
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
