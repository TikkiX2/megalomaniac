<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Ai\Services\ChatService;
use App\Ai\Tools\RememberMemoryTool;
use App\Models\AgentDefinition;
use App\Models\ChatThread;
use App\Models\User;

test('megalomaniac agent injects global and thread memories with ids', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $catalog = app(MemoryCatalog::class);
    $global = $catalog->remember($user, 'Prefiere entrenar a la mañana', MemoryScope::Global);
    $threadMemory = $catalog->remember($user, 'Objetivo del hilo: PR de press banca', MemoryScope::Thread, $thread);

    $instructions = (new MegalomaniacAgent($user, ['*'], $thread))->instructions();

    expect($instructions)->toContain('Memoria general del usuario')
        ->and($instructions)->toContain($global->id)
        ->and($instructions)->toContain('Memoria de este hilo')
        ->and($instructions)->toContain($threadMemory->id)
        ->and($instructions)->toContain('nunca guardes credenciales');
});

test('memory is neither injected nor available when the group is disabled', function () {
    $user = User::factory()->create();
    app(MemoryCatalog::class)->remember($user, 'Dato general', MemoryScope::Global);

    $agent = new MegalomaniacAgent($user, ['tasks']);

    expect($agent->instructions())->not->toContain('Memoria general');

    $classes = collect(iterator_to_array($agent->tools()))->map(fn ($tool): string => $tool::class);

    expect($classes)->not->toContain(RememberMemoryTool::class);
});

test('agent without thread only injects global memory', function () {
    $user = User::factory()->create();
    app(MemoryCatalog::class)->remember($user, 'Dato general', MemoryScope::Global);

    $instructions = (new MegalomaniacAgent($user))->instructions();

    expect($instructions)->toContain('Memoria general del usuario')
        ->and($instructions)->not->toContain('Memoria de este hilo');
});

test('runtime agent injects the global memory block', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->create(['user_id' => $user->id]);

    app(MemoryCatalog::class)->remember($user, 'El usuario prefiere reportes cortos', MemoryScope::Global);

    $instructions = (new RuntimeAgent($definition))->instructions();

    expect($instructions)->toContain('El usuario prefiere reportes cortos');
});

test('auto tool policy always includes the memory group', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $policy = app(ChatService::class)
        ->prepareToolPolicy($thread, '¿Qué tareas tengo pendientes?');

    expect($policy['mode'])->toBe('auto')
        ->and($policy['groups'])->toContain('memory')
        ->and($policy['groups'])->toContain('tasks');

    $manual = app(ChatService::class)
        ->prepareToolPolicy($thread, '¿Qué tareas tengo pendientes?', ['mode' => 'manual', 'groups' => ['tasks']]);

    expect($manual['groups'])->toBe(['tasks']);
});
