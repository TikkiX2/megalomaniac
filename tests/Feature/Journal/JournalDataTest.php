<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('cifra el cuerpo privado y lo devuelve legible', function () {
    $user = User::factory()->create();
    $day = JournalDay::factory()->for($user)->create([
        'date' => '2026-11-15',
        'body' => 'algo íntimo',
        'is_private' => true,
    ]);

    $raw = DB::table('journal_days')->where('id', $day->id)->value('body');

    expect($raw)->toStartWith('enc:v1:')
        ->and($raw)->not->toContain('algo íntimo')
        ->and($day->fresh()->body)->toBe('algo íntimo');
});

it('guarda el cuerpo público en claro', function () {
    $day = JournalDay::factory()->create(['date' => '2026-11-14', 'body' => 'día normal', 'is_private' => false]);

    expect(DB::table('journal_days')->where('id', $day->id)->value('body'))->toBe('día normal');
});

it('cifra al marcar privado y descifra al volver a público', function () {
    $day = JournalDay::factory()->create(['date' => '2026-11-13', 'body' => 'secreto', 'is_private' => false]);

    $day->update(['is_private' => true]);
    expect(DB::table('journal_days')->where('id', $day->id)->value('body'))->toStartWith('enc:v1:');

    $day->refresh()->update(['is_private' => false]);
    expect(DB::table('journal_days')->where('id', $day->id)->value('body'))->toBe('secreto')
        ->and($day->fresh()->body)->toBe('secreto');
});

it('cifra también el título del momento privado', function () {
    $moment = JournalMoment::factory()->create(['title' => 'Título privado', 'body' => 'cuerpo', 'is_private' => true]);

    $raw = DB::table('journal_moments')->where('id', $moment->id)->first();

    expect($raw->title)->toStartWith('enc:v1:')
        ->and($raw->title)->not->toContain('Título privado')
        ->and($moment->fresh()->title)->toBe('Título privado');
});

it('no permite dos días del mismo usuario en la misma fecha', function () {
    $user = User::factory()->create();
    JournalDay::factory()->for($user)->create(['date' => '2026-11-12']);

    expect(fn () => JournalDay::factory()->for($user)->create(['date' => '2026-11-12']))
        ->toThrow(QueryException::class);
});

it('permite la misma fecha a usuarios distintos', function () {
    JournalDay::factory()->create(['date' => '2026-11-11']);
    JournalDay::factory()->create(['date' => '2026-11-11']);

    expect(JournalDay::where('date', '2026-11-11')->count())->toBe(2);
});

it('considera contenido el ánimo solo, pero no las etiquetas solas', function () {
    $conMood = JournalDay::factory()->create(['mood' => 4, 'body' => null]);
    $soloTags = JournalDay::factory()->create(['mood' => null, 'body' => null, 'tags' => ['viaje']]);

    expect($conMood->hasContent())->toBeTrue()
        ->and($soloTags->hasContent())->toBeFalse();
});

it('no deja que un usuario vea ni edite memorias ajenas', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $moment = JournalMoment::factory()->create(['user_id' => $owner->id]);

    expect($intruder->can('update', $moment))->toBeFalse()
        ->and($owner->can('update', $moment))->toBeTrue();
});
