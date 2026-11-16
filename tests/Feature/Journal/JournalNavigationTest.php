<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create());
});

it('expone las cuatro páginas del diario al usuario autenticado', function () {
    foreach (['/diario', '/diario/2026-11-15', '/diario/calendario', '/diario/todas', '/diario/repaso'] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get('/diario/buscar?q=diario')->assertOk();
});
