<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Gate de la UI de Settings → IA: el esqueleto de pestañas y la tabla de
// proveedores (con su badge de salud) se alimentan de estos props. Si la
// reescritura de `settings/ai.tsx` rompe el render o el backend deja de
// exponer la forma que la página consume, este test cae.
test('settings ai page renders the provider tab with health badges data', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->get(route('settings.ai.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('providers.0.has_key', true)
            ->has('scopes', 14)
            ->where('ai.ai_enabled', true));
});

// Gate de la pestaña «Asignaciones»: la matriz de scopes se construye con el
// orden fijo de `AiScope::cases()` (global, superficies, módulos) y cada fila
// trae la cadena guardada (`[]` = hereda) más la efectiva. La UI agrupa por
// `section`, así que el orden de las props es el contrato.
test('assignments tab data lists global, surfaces and modules', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->get(route('settings.ai.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('scopes.0.scope', 'global')
            ->where('scopes.1.scope', 'surface:chat')
            ->where('scopes.6.scope', 'surface:files')
            ->where('scopes.7.scope', 'module:gym')
            ->where('scopes.0.chain', [])
            ->where('scopes.1.section', 'surface')
            ->where('scopes.7.section', 'module')
            ->where('scopes.0.label', 'Global')
            ->where('scopes.6.label', 'Archivos (visión/OCR)')
            ->where('scopes.7.label', 'Gimnasio')
            ->where('module_labels.gym', 'Gimnasio')
            ->where('scopes.0.effective', fn ($ids) => count($ids) === 1));
});

// Regresión de QA (Task 15): el selector de scope de la pestaña «Prompts» agrupaba
// sus opciones con un `SelectLabel` desnudo. Radix lee ese label del contexto de
// `SelectGroup`, así que abrir la pestaña tiraba
// «`SelectLabel` must be used within `SelectGroup`» y React desmontaba la página
// entera (pantalla en blanco). El repo no tiene runner de tests JS, así que la
// guarda es estructural sobre el componente.
test('every SelectLabel of the settings ai page lives inside a SelectGroup', function () {
    $source = file_get_contents(resource_path('js/pages/settings/ai.tsx'));

    // Cada apertura de `SelectLabel` debe caer dentro de un `SelectGroup`: se
    // recorre el archivo y, para cada label, se exige que el último `SelectGroup`
    // sin cerrar esté abierto.
    preg_match_all('/<(\/?)Select(Group|Label)\b/', $source, $matches, PREG_OFFSET_CAPTURE);

    $depth = 0;
    $labelsOutsideGroup = [];

    foreach ($matches[0] as [$tag, $offset]) {
        if ($tag === '<SelectGroup') {
            $depth++;
        } elseif ($tag === '</SelectGroup') {
            $depth--;
        } elseif ($depth < 1) {
            $labelsOutsideGroup[] = $offset;
        }
    }

    expect($depth)->toBe(0, 'SelectGroup openings and closings must balance')
        ->and($labelsOutsideGroup)->toBe([], 'SelectLabel must be wrapped in SelectGroup');

    expect($source)->toContain('SelectGroup,');
});
