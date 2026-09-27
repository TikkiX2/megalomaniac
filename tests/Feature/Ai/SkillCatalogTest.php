<?php

use App\Ai\Skills\SkillCatalog;
use App\Ai\Tools\LoadSkillTool;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('importing markdown parses frontmatter and strips it from the instructions', function () {
    $user = User::factory()->create();
    $catalog = app(SkillCatalog::class);

    $markdown = <<<'MD'
---
name: Brainstorming
description: Explora intención y requisitos antes de implementar
---

# Brainstorming

Paso 1: clasificar el pedido.
MD;

    $skill = $catalog->importFromMarkdown($user, $markdown, 'brainstorming.md');

    expect($skill->name)->toBe('Brainstorming')
        ->and($skill->description)->toBe('Explora intención y requisitos antes de implementar')
        ->and($skill->source)->toBe('import')
        ->and($skill->instructions)->toStartWith('# Brainstorming')
        ->and($skill->instructions)->not->toContain('name: Brainstorming');
});

test('importing the same skill twice updates it instead of duplicating', function () {
    $user = User::factory()->create();
    $catalog = app(SkillCatalog::class);

    $first = $catalog->importFromMarkdown($user, "# Cocina\n\nVersion 1", 'cocina.md');
    $second = $catalog->importFromMarkdown($user, "# Cocina\n\nVersion 2", 'cocina.md');

    expect($second->id)->toBe($first->id)
        ->and($second->instructions)->toContain('Version 2')
        ->and(Skill::query()->forUser($user)->count())->toBe(1);
});

test('the catalog enforces the per user limit', function () {
    $user = User::factory()->create();
    $catalog = app(SkillCatalog::class);

    Skill::factory()->count(SkillCatalog::MAX_SKILLS)->create(['user_id' => $user->id]);

    expect(fn () => $catalog->create($user, ['name' => 'Extra', 'instructions' => 'x']))
        ->toThrow(ValidationException::class);
});

test('the load skill tool returns the instructions or a helpful error', function () {
    $user = User::factory()->create();
    $catalog = app(SkillCatalog::class);
    $skill = $catalog->create($user, ['name' => 'Brainstorming', 'instructions' => 'Clasificá el pedido.']);
    $tool = new LoadSkillTool($user, $catalog);

    $found = (string) $tool->handle(new Request(['skill' => $skill->key]));

    expect($found)->toBe('Clasificá el pedido.');

    $missing = (string) $tool->handle(new Request(['skill' => 'nope']));

    expect($missing)->toContain('No existe la skill')
        ->and($missing)->toContain($skill->key);

    $skill->forceFill(['enabled' => false])->save();

    $disabled = (string) $tool->handle(new Request(['skill' => $skill->key]));

    expect($disabled)->toContain('No existe la skill');
});

test('the import command loads the bundled skills for a user', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->artisan('skills:import', ['--user' => $user->id])->assertSuccessful();

    $keys = Skill::query()->forUser($user)->pluck('key')->all();

    expect($keys)->toHaveCount(5)
        ->and($keys)->toContain('brainstorming')
        ->and($keys)->toContain('using-superpowers')
        ->and($keys)->toContain('writing-plans')
        ->and($keys)->toContain('anti-ui-slop')
        ->and($keys)->toContain('ui-radar');
});

test('frontmatter parsing handles quoted values with colons and escapes', function () {
    $user = User::factory()->create();

    $skill = app(SkillCatalog::class)->importFromMarkdown($user, <<<'MD'
---
name: Brainstorming
description: "Explora: intención y \"gates\" antes de implementar"
---

# Cuerpo
MD, 'brainstorming.md');

    expect($skill->name)->toBe('Brainstorming')
        ->and($skill->description)->toBe('Explora: intención y "gates" antes de implementar');
});
