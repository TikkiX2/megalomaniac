<?php

use App\Ai\Agents\AgentRunner;
use App\Ai\Services\InsightService;
use App\Ai\Support\AiAllProvidersFailedException;
use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiInsightOutcome;
use App\Feed\FeedRanker;
use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\AiProvider;
use App\Models\FeedItem;
use App\Models\FeedPreference;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserAiScope;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

/** Non-streaming OpenAI-compatible body: `{choices:[{message:{content}}]}`. */
function callerChatBody(string $content): array
{
    return [
        'model' => 'qa',
        'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ];
}

test('insight callers resolve their module scope', function () {
    $user = User::factory()->withAiProvider()->create();          // provider A → api.example.com
    $gym = AiProvider::factory()->for($user)->create([
        'name' => 'Gym',
        'url' => 'https://gym.example.com/v1',
        'sort_order' => 5,
    ]);
    $finance = AiProvider::factory()->for($user)->create([
        'name' => 'Finance',
        'url' => 'https://finance.example.com/v1',
        'sort_order' => 6,
    ]);

    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$gym->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:finance', 'provider_chain' => [$finance->id]]);

    Workout::factory()->for($user)->create(['started_at' => now()]);

    Http::fake([
        'gym.example.com/*' => Http::response(callerChatBody('GYM SCOPE'), 200),
        'finance.example.com/*' => Http::response(callerChatBody('FINANCE SCOPE'), 200),
        'api.example.com/*' => Http::response(callerChatBody('GLOBAL SCOPE'), 200),
    ]);

    $service = app(InsightService::class);

    expect($service->generateWorkoutInsights($user))->toBe('GYM SCOPE')
        ->and($service->generateFinanceInsights($user))->toBe('FINANCE SCOPE');

    $hosts = Http::recorded()
        ->map(fn ($pair): string => parse_url($pair[0]->url(), PHP_URL_HOST))
        ->all();

    expect($hosts)->toContain('gym.example.com', 'finance.example.com')
        ->not->toContain('api.example.com');
});

test('agent runner falls back when the primary provider fails', function () {
    $user = User::factory()->withAiProvider()->create();          // primary → api.example.com
    $primary = AiProvider::query()->where('user_id', $user->id)->firstOrFail();
    $backup = AiProvider::factory()->for($user)->create([
        'name' => 'Backup',
        'url' => 'https://backup.example.com/v1',
        'sort_order' => 1,
    ]);

    $definition = AgentDefinition::factory()->for($user)->create();

    Http::fake([
        'api.example.com/*' => Http::response(['error' => ['message' => 'down']], 500),
        'backup.example.com/*' => Http::response(callerChatBody('{"report":"ok","suggestions":null,"notify":null}'), 200),
    ]);

    $run = app(AgentRunner::class)->run($definition, 'manual');

    expect($run->status)->toBe(AgentRun::STATUS_SUCCESS)
        ->and($run->report)->toBe('ok')
        ->and(Http::recorded())->toHaveCount(2)
        ->and($backup->id)->not->toBe($primary->id)
        ->and((new AiHealthService)->statusFor($primary)->consecutive_failures)->toBe(1);
});

test('embeddings resolve the embeddings scope provider', function () {
    Embeddings::fake();
    Http::fake(['*' => Http::response(['error' => ['message' => 'no llm here']], 401)]);

    $user = User::factory()->withAiProvider()->create();          // provider without embeddings_model
    $provider = AiProvider::query()->where('user_id', $user->id)->firstOrFail();

    // The lexical weights favour `far`, so only the embedding path can rank `near` first.
    FeedPreference::forUser($user)->update(['embedding' => [1.0, 0.0], 'topic_weights' => ['receta' => 5.0]]);

    $near = FeedItem::factory()->for($user)->create(['title' => 'Novedades de Laravel', 'embedding' => [1.0, 0.0]]);
    $far = FeedItem::factory()->for($user)->create(['title' => 'Receta de pan', 'embedding' => [0.0, 1.0]]);

    // No embeddings model on the resolved provider: lexical ranking, no embedding call.
    $ranked = app(FeedRanker::class)->top($user, 10);

    expect($ranked->first()->id)->toBe($far->id);
    Embeddings::assertNothingGenerated();

    // Once the resolved provider carries an embeddings model the scope is used,
    // and the vectors are requested with that model.
    $provider->update(['embeddings_model' => 'embed-1']);
    $near->forceFill(['embedding' => null])->save();
    $far->forceFill(['embedding' => null])->save();

    app(FeedRanker::class)->top($user, 10);

    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->model === 'embed-1');
});

/** An insight-endpoint user with the data each endpoint needs to reach the provider. */
function insightEndpointUser(): User
{
    $user = User::factory()->withAiProvider()->create();          // single provider: exhaustion still throws

    Workout::factory()->for($user)->create(['started_at' => now()]);
    MealLog::create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'meal_type' => 'lunch',
    ]);

    return $user;
}

test('insight endpoints answer 200 with an honest message when every provider fails', function () {
    Exceptions::fake();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $user = insightEndpointUser();

    // The four `InsightService` methods surface the honest copy as the insight.
    $this->actingAs($user)->getJson(route('ai.insights.workout'))
        ->assertOk()
        ->assertJsonPath('insight', AiInsightOutcome::EXHAUSTED_MESSAGE)
        ->assertJsonPath('message', null);

    $this->actingAs($user)->getJson(route('ai.insights.nutrition'))
        ->assertOk()
        ->assertJsonPath('insight', null)
        ->assertJsonPath('message', AiInsightOutcome::EXHAUSTED_MESSAGE);

    $this->actingAs($user)->getJson(route('ai.suggest-meal'))
        ->assertOk()
        ->assertJsonPath('suggestion', null)
        ->assertJsonPath('message', AiInsightOutcome::EXHAUSTED_MESSAGE);

    $this->actingAs($user)->getJson(route('ai.generate-routine'))
        ->assertOk()
        ->assertJsonPath('routine', null)
        ->assertJsonPath('message', AiInsightOutcome::EXHAUSTED_MESSAGE);

    $this->actingAs($user)->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description', null)
        ->assertJsonPath('message', AiInsightOutcome::EXHAUSTED_MESSAGE);

    $this->actingAs($user)->postJson(route('ai.generate-quote'), [
        'client_name' => 'Acme',
        'project_description' => 'Landing page',
    ])
        ->assertOk()
        ->assertJsonPath('suggestion', null)
        ->assertJsonPath('message', AiInsightOutcome::EXHAUSTED_MESSAGE);

    // Each caller reports the exhausted chain exactly once.
    Exceptions::assertReported(AiAllProvidersFailedException::class);
});

test('insight endpoints keep the not configured copy when no provider exists', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create(['ai_enabled' => true]);
    Workout::factory()->for($user)->create(['started_at' => now()]);
    MealLog::create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'meal_type' => 'lunch',
    ]);

    $this->actingAs($user)->getJson(route('ai.insights.workout'))
        ->assertOk()
        ->assertJsonPath('insight', null)
        ->assertJsonPath('message', 'AI not configured or no data available.');

    $this->actingAs($user)->getJson(route('ai.insights.nutrition'))
        ->assertOk()
        ->assertJsonPath('insight', null)
        ->assertJsonPath('message', 'AI not configured.');

    $this->actingAs($user)->getJson(route('ai.suggest-meal'))
        ->assertOk()
        ->assertJsonPath('suggestion', null)
        ->assertJsonPath('message', 'AI not configured. Please enable AI in Settings.');

    $this->actingAs($user)->getJson(route('ai.generate-routine'))
        ->assertOk()
        ->assertJsonPath('routine', null)
        ->assertJsonPath('message', 'AI not configured. Please enable AI in Settings.');

    $this->actingAs($user)->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description', null)
        ->assertJsonPath('message', 'AI not configured.');

    $this->actingAs($user)->postJson(route('ai.generate-quote'), [
        'client_name' => 'Acme',
        'project_description' => 'Landing page',
    ])
        ->assertOk()
        ->assertJsonPath('suggestion', null)
        ->assertJsonPath('message', 'AI not configured.');
});
