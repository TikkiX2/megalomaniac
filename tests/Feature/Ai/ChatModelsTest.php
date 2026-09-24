<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('models endpoint returns provider models as json', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::response([
            'data' => [['id' => 'model-a'], ['id' => 'model-b']],
        ]),
    ]);

    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->getJson(route('ai.models'))
        ->assertOk()
        ->assertJson(['models' => ['model-a', 'model-b']]);
});

test('models endpoint falls back to configured model', function () {
    Http::fake(['api.example.com/v1/models' => Http::response(null, 500)]);

    $user = User::factory()->withAiProvider('solo-este')->create();

    $this->actingAs($user)
        ->getJson(route('ai.models'))
        ->assertOk()
        ->assertJson(['models' => ['solo-este']]);
});

test('refresh query param bypasses the cache', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::sequence()
            ->push(['data' => [['id' => 'viejo']]])
            ->push(['data' => [['id' => 'nuevo']]]),
    ]);

    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->getJson(route('ai.models'))->assertJson(['models' => ['viejo']]);
    $this->actingAs($user)->getJson(route('ai.models'))->assertJson(['models' => ['viejo']]);
    $this->actingAs($user)->getJson(route('ai.models', ['refresh' => 1]))->assertJson(['models' => ['nuevo']]);

    Http::assertSentCount(2);
});
