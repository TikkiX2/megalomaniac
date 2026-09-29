<?php

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks access to another users routine through web routes', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)->getJson("/gym/routines/{$routine->id}")->assertForbidden();
    $this->actingAs($user)->putJson("/gym/routines/{$routine->id}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($user)->deleteJson("/gym/routines/{$routine->id}")->assertForbidden();
});
