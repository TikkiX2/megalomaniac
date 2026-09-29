<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

test('projects index is accessible', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('freelance.projects.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('freelance/projects/Index')
        );
});

test('project can be created', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    $currency = Currency::where('code', 'USD')->first() ?? Currency::factory()->create(['code' => 'USD']);

    $projectData = [
        'client_id' => $client->id,
        'name' => 'Test Project',
        'description' => null,
        'status' => 'pending',
        'currency_id' => $currency->id,
        'total_amount' => 1000,
        'area' => 'Web',
        'module' => 'Core',
    ];

    actingAs($user)
        ->post(route('freelance.projects.store'), $projectData)
        ->assertRedirect(); // Redirects to show

    assertDatabaseHas('projects', [
        'name' => 'Test Project',
        'client_id' => $client->id,
    ]);
});

test('project show page is accessible', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get(route('freelance.projects.show', $project))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('freelance/projects/Show')
        );
});

test('projects index excludes personal projects', function () {
    $user = User::factory()->create();
    Project::factory()->create(['user_id' => $user->id, 'type' => 'personal', 'name' => 'Personal Oculto']);
    Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance', 'name' => 'Freelance Visible']);

    actingAs($user)
        ->get(route('freelance.projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freelance/projects/Index')
            ->where('projects.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Freelance Visible']));
});

test('freelance dashboard excludes personal projects', function () {
    $user = User::factory()->create();
    Project::factory()->create(['user_id' => $user->id, 'type' => 'personal', 'name' => 'Personal Oculto', 'status' => 'in_progress']);
    Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance', 'name' => 'Freelance Visible', 'status' => 'in_progress']);

    actingAs($user)
        ->get(route('freelance.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('freelance/Dashboard')
            ->where('stats.active_projects', 1)
            ->where('recent_projects', fn ($rows) => collect($rows)->pluck('name')->all() === ['Freelance Visible']));
});
