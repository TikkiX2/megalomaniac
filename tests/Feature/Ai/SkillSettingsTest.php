<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('skills are managed from settings with per user isolation', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($user)->get(route('skills.index'))->assertOk();

    $this->actingAs($user)
        ->post(route('skills.store'), [
            'name' => 'Brainstorming',
            'description' => 'Explorar antes de construir',
            'instructions' => 'Clasificá el pedido.',
            'enabled' => true,
        ])
        ->assertRedirect(route('skills.index'));

    $skill = Skill::query()->forUser($user)->sole();

    expect($skill->key)->toBe('brainstorming');

    $this->actingAs($user)
        ->patch(route('skills.update', $skill), ['name' => 'Brainstorming v2'])
        ->assertRedirect(route('skills.index'));

    expect($skill->refresh()->name)->toBe('Brainstorming v2');

    $this->actingAs($user)
        ->patch(route('skills.toggle', $skill), ['enabled' => false])
        ->assertRedirect(route('skills.index'));

    expect($skill->refresh()->enabled)->toBeFalse();

    $this->actingAs($intruder)->patch(route('skills.toggle', $skill), ['enabled' => true])->assertNotFound();
    $this->actingAs($intruder)->delete(route('skills.destroy', $skill))->assertNotFound();

    $this->actingAs($user)->delete(route('skills.destroy', $skill))->assertRedirect(route('skills.index'));

    expect(Skill::query()->forUser($user)->count())->toBe(0);
});

test('a SKILL.md file can be imported from settings', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('brainstorming.md', <<<'MD'
---
name: Brainstorming
description: Explorar antes de construir
---

# Brainstorming

Clasificá el pedido.
MD);

    $this->actingAs($user)
        ->post(route('skills.import'), ['file' => $file])
        ->assertRedirect(route('skills.index'));

    $skill = Skill::query()->forUser($user)->sole();

    expect($skill->name)->toBe('Brainstorming')
        ->and($skill->source)->toBe('import')
        ->and($skill->instructions)->toContain('Clasificá el pedido.');
});

test('selecting a skill for a turn injects its instructions into the prompt', function () {
    Http::fake(['*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $user = User::factory()->withAiProvider()->create();
    $skill = Skill::factory()->create([
        'user_id' => $user->id,
        'name' => 'Brainstorming',
        'key' => 'brainstorming',
        'instructions' => 'INSTRUCCION_UNICA_DE_LA_SKILL',
    ]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'quiero redecorar mi cuarto',
        'skill_keys' => [$skill->key],
    ])->assertOk()->streamedContent();

    Http::assertSent(function ($request): bool {
        $body = (string) $request->body();

        return str_contains($body, 'INSTRUCCION_UNICA_DE_LA_SKILL')
            && str_contains($body, 'Brainstorming');
    });
});

test('a disabled skill cannot be selected for a turn', function () {
    Http::fake();

    $user = User::factory()->withAiProvider()->create();
    $skill = Skill::factory()->disabled()->create(['user_id' => $user->id, 'key' => 'apagada']);

    $this->actingAs($user)->postJson(route('ai.chat.send'), [
        'message' => 'hola',
        'skill_keys' => [$skill->key],
    ])->assertStatus(422)->assertJsonValidationErrors('skill_keys.0');

    Http::assertNothingSent();
});

test('the agent advertises available skills when the skills tool is enabled', function () {
    $user = User::factory()->withAiProvider()->create();
    Skill::factory()->create([
        'user_id' => $user->id,
        'name' => 'Cocina rápida',
        'key' => 'cocina-rapida',
        'description' => 'Recetas en 15 minutos',
    ]);

    $agent = new MegalomaniacAgent($user, ['tasks', 'skills']);

    expect($agent->instructions())
        ->toContain('Skills disponibles')
        ->toContain('cocina-rapida — Recetas en 15 minutos');

    $without = new MegalomaniacAgent($user, ['tasks']);

    expect($without->instructions())->not->toContain('Skills disponibles');
});
