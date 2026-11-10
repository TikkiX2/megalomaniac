<?php

use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('uses the user local day on the today dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 01:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 01:00 UTC del 10/11 == 22:00 local del 9/11.
    $this->actingAs($user)->get('/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('date', '2026-11-09'));
});
