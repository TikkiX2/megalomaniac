<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can login and receive token', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'test-device',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
});

test('login fails with invalid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'test-device',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

test('authenticated user can get current user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', $user->email);
});

test('unauthenticated user cannot access me endpoint', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Logged out');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'token' => hash('sha256', $token),
    ]);
});

test('unauthenticated user cannot access logout endpoint', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});

test('login requires email to be valid format', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => 'password',
        'device_name' => 'test-device',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
