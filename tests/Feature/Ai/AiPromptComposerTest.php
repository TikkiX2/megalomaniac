<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Enums\AiScope;
use App\Ai\Support\AiPromptComposer;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('composes global, module and surface layers in order', function () {
    $user = User::factory()->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => 'Siempre en español.']);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'prompt' => 'Céntrate en hipertrofia.']);
    UserAiScope::factory()->for($user)->create(['scope' => 'surface:chat', 'prompt' => 'Respuestas cortas.']);

    $block = (new AiPromptComposer)->personalizationBlock($user, 'gym', AiScope::SurfaceChat->value);

    expect($block)
        ->toContain('## Personalización global')
        ->toContain('## Personalización: Gimnasio')
        ->toContain('## Personalización: Chat')
        ->toContain('Siempre en español.')
        ->toContain('Céntrate en hipertrofia.')
        ->toContain('Respuestas cortas.')
        ->and(strpos($block, '## Personalización global'))
        ->toBeLessThan(strpos($block, '## Personalización: Gimnasio'))
        ->and(strpos($block, '## Personalización: Gimnasio'))
        ->toBeLessThan(strpos($block, '## Personalización: Chat'));
});

test('skips empty layers and returns null when no layer exists', function () {
    $user = User::factory()->create();
    $composer = new AiPromptComposer;

    expect($composer->personalizationBlock($user, 'gym', 'surface:chat'))->toBeNull();

    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => '   ']);

    expect($composer->personalizationBlock($user, 'gym', 'surface:chat'))->toBeNull();

    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'prompt' => '  ']);

    expect($composer->personalizationBlock($user, 'gym', 'surface:chat'))->toBeNull();
});

test('personalization lands after the base and before the skills blocks', function () {
    $user = User::factory()->create();
    Skill::factory()->create([
        'user_id' => $user->id,
        'name' => 'Cocina rápida',
        'key' => 'cocina-rapida',
        'description' => 'Recetas en 15 minutos',
    ]);

    $agent = (new MegalomaniacAgent($user))->withPersonalization("## Personalización global\n\nHablá en criollo.");

    $instructions = $agent->instructions();

    $personalization = strpos($instructions, 'Hablá en criollo.');
    $baseOpener = strpos($instructions, 'You are Megalomaniac AI');
    $writeInstructions = strpos($instructions, 'When the user asks to perform an action');
    $skillsBlock = strpos($instructions, 'Skills disponibles');

    expect($personalization)->not->toBeFalse()
        ->and($baseOpener)->not->toBeFalse()
        ->and($skillsBlock)->not->toBeFalse()
        ->and($personalization)->toBeGreaterThan($baseOpener)
        ->and($personalization)->toBeLessThan($skillsBlock);

    if ($writeInstructions !== false) {
        expect($personalization)->toBeLessThan($writeInstructions);
    }
});

test('instructions are unchanged when no personalization block is given', function () {
    $user = User::factory()->create();

    expect((new MegalomaniacAgent($user))->instructions())
        ->toBe((new MegalomaniacAgent($user))->withPersonalization(null)->instructions())
        ->not->toContain('Personalización');
});

test('preview concatenates the base instructions with the block', function () {
    $composer = new AiPromptComposer;

    expect($composer->preview('base', null))->toBe('base')
        ->and($composer->preview('base', '## Personalización global'))->toBe("base\n\n## Personalización global")
        ->and($composer->preview(null, 'block'))->toBe('block')
        ->and($composer->preview(null, null))->toBe('');
});
