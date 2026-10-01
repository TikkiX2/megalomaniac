<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Enums\AiScope;
use App\Ai\Support\AiScopeResolver;
use App\Models\AiProvider;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A thread the rails can list: the chat queries filter on `withMessages()`, so
 * an empty thread never shows up in a rail.
 *
 * @param  array<string, mixed>  $attributes
 */
function moduleChatThread(User $user, array $attributes = []): ChatThread
{
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        ...$attributes,
    ]);

    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    return $thread;
}

test('sending from a module route creates a thread tagged with the module', function () {
    MegalomaniacAgent::fake(['OK']);

    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Cómo voy?', 'module' => 'gym'])
        ->assertOk();

    expect(ChatThread::query()->sole()->module)->toBe('gym');
});

test('an unknown module is rejected instead of tagging the thread', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'Hola', 'module' => 'ajedrez'])
        ->assertSessionHasErrors('module');

    expect(ChatThread::query()->count())->toBe(0);
});

test('module page lists only its threads', function () {
    $user = User::factory()->withAiProvider()->create();
    moduleChatThread($user, ['title' => 'Gym', 'module' => 'gym']);
    $finance = moduleChatThread($user, ['title' => 'Finanzas', 'module' => 'finance']);

    $this->actingAs($user)
        ->get('/ai/finance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/module')
            ->where('module', 'finance')
            ->has('threads', 1)
            ->where('threads.0.id', $finance->id)
            ->has('models')
            ->has('ai.configured')
        );
});

test('health module threads come from the existing salud category', function () {
    $user = User::factory()->withAiProvider()->create();
    $salud = moduleChatThread($user, ['title' => 'Salud', 'category' => ChatThread::CATEGORY_HEALTH]);
    // Born in the health rail (module column), it belongs to the same history.
    $born = moduleChatThread($user, ['title' => 'Salud nueva', 'module' => 'health']);
    moduleChatThread($user, ['title' => 'Gym', 'module' => 'gym']);

    $this->actingAs($user)
        ->get('/ai/health')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/module')
            ->where('module', 'health')
            ->has('threads', 2)
            ->where('threads.0.id', $salud->id)
            ->where('threads.1.id', $born->id)
        );
});

test('an unknown module key is not a module page', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->get('/ai/ajedrez')->assertNotFound();
});

test('module scope takes part in provider resolution for the chat turn', function () {
    $user = User::factory()->withAiProvider()->create();
    $global = AiProvider::factory()->for($user)->create(['sort_order' => 0]);
    $gymProvider = AiProvider::factory()->for($user)->create(['url' => 'https://gym.example.com/v1', 'sort_order' => 5]);
    UserAiScope::factory()->for($user)->create(['scope' => AiScope::Global->value, 'provider_chain' => [$global->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => AiScope::ModuleGym->value, 'provider_chain' => [$gymProvider->id]]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat, 'gym');

    expect($resolution->primary()->id)->toBe($gymProvider->id);
});

test('a module thread streams its turn on the module chain', function () {
    MegalomaniacAgent::fake(['OK']);

    $user = User::factory()->withAiProvider()->create();
    $global = AiProvider::factory()->for($user)->create(['name' => 'Global', 'sort_order' => 0]);
    $gymProvider = AiProvider::factory()->for($user)->create(['name' => 'Gym provider', 'sort_order' => 5]);
    UserAiScope::factory()->for($user)->create(['scope' => AiScope::Global->value, 'provider_chain' => [$global->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => AiScope::ModuleGym->value, 'provider_chain' => [$gymProvider->id]]);

    $thread = moduleChatThread($user, ['module' => 'gym']);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Cómo va?', 'thread_id' => $thread->id])
        ->streamedContent();

    expect($content)
        ->toContain('"type":"meta"')
        ->toContain('"provider":"Gym provider"');
});

test('a salud thread without a module column still resolves the health scope', function () {
    MegalomaniacAgent::fake(['OK']);

    $user = User::factory()->withAiProvider()->create();
    $health = AiProvider::factory()->for($user)->create(['name' => 'Health provider', 'sort_order' => 5]);
    UserAiScope::factory()->for($user)->create(['scope' => AiScope::ModuleHealth->value, 'provider_chain' => [$health->id]]);

    $thread = moduleChatThread($user, ['category' => ChatThread::CATEGORY_HEALTH]);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => '¿Cómo duermo?', 'thread_id' => $thread->id])
        ->streamedContent();

    expect($content)->toContain('"provider":"Health provider"');
});
