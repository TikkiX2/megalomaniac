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
            ->has('scopes', 13)
            ->where('ai.ai_enabled', true));
});
