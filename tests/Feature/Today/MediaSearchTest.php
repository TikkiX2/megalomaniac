<?php

use App\Models\QueueItem;
use App\Models\User;
use App\Services\Media\MediaSearchService;
use Illuminate\Support\Facades\Http;

test('media search normaliza resultados de open library', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response([
            'docs' => [[
                'title' => 'Dune',
                'author_name' => ['Frank Herbert'],
                'first_publish_year' => 1965,
                'cover_i' => 123,
                'key' => '/works/OL1W',
            ]],
        ], 200),
        '*' => Http::response('boom', 500),
    ]);

    $res = app(MediaSearchService::class)->search('libro', 'dune');

    expect($res)->toHaveCount(1);
    expect($res[0]->title)->toBe('Dune');
    expect($res[0]->creator)->toBe('Frank Herbert');
    expect($res[0]->year)->toBe(1965);
    expect($res[0]->cover_url)->toBe('https://covers.openlibrary.org/b/id/123-M.jpg');
    expect($res[0]->source)->toBe('openlibrary');
    expect($res[0]->external_id)->toBe('/works/OL1W');
    expect($res[0]->toArray())->toBe([
        'title' => 'Dune',
        'creator' => 'Frank Herbert',
        'year' => 1965,
        'cover_url' => 'https://covers.openlibrary.org/b/id/123-M.jpg',
        'source' => 'openlibrary',
        'external_id' => '/works/OL1W',
    ]);
});

test('media search tolera fallas del provider', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(app(MediaSearchService::class)->search('pelicula', 'dune'))->toBe([]);
});

test('media search cachea 24h', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(['docs' => []], 200),
        '*' => Http::response('boom', 500),
    ]);

    app(MediaSearchService::class)->search('libro', 'dune');
    app(MediaSearchService::class)->search('libro', 'dune');

    Http::assertSentCount(1);
});

test('media search con query muy corta o tipo invalido devuelve vacio', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(app(MediaSearchService::class)->search('libro', 'd'))->toBe([]);
    expect(app(MediaSearchService::class)->search('libro', '   '))->toBe([]);
    expect(app(MediaSearchService::class)->search('musica', 'dune'))->toBe([]);
    Http::assertNothingSent();
});

test('media search normaliza resultados de wikidata', function () {
    Http::fake([
        '*action=wbsearchentities*' => Http::response([
            'search' => [
                ['id' => 'Q600123', 'label' => 'Dune'],
                ['id' => 'Q7257', 'label' => 'Isaac Asimov'],
            ],
        ], 200),
        '*action=wbgetentities*' => Http::response([
            'entities' => [
                'Q600123' => ['claims' => [
                    'P31' => [claimValue(['id' => 'Q11424'])],
                    'P577' => [claimValue(['time' => '+2021-10-22T00:00:00Z', 'timezone' => 0, 'before' => 0, 'after' => 0])],
                    'P18' => [claimValue('Dune2021Poster.jpg')],
                ]],
                'Q7257' => ['claims' => [
                    'P31' => [claimValue(['id' => 'Q5'])],
                ]],
            ],
        ], 200),
        '*' => Http::response('boom', 500),
    ]);

    $res = app(MediaSearchService::class)->search('pelicula', 'dune');

    expect($res)->toHaveCount(1);
    expect($res[0]->title)->toBe('Dune');
    expect($res[0]->year)->toBe(2021);
    expect($res[0]->cover_url)->toBe('https://commons.wikimedia.org/wiki/Special:FilePath/Dune2021Poster.jpg?width=200');
    expect($res[0]->source)->toBe('wikidata');
    expect($res[0]->external_id)->toBe('Q600123');
});

test('media search normaliza resultados de musicbrainz y cover 404 da null', function () {
    Http::fake([
        '*musicbrainz.org/ws/2/release-group*' => Http::response([
            'release-groups' => [[
                'id' => 'abc-123',
                'title' => 'Random Access Memories',
                'first-release-date' => '2013-05-17',
                'artist-credit' => [['name' => 'Daft Punk']],
            ]],
        ], 200),
        '*coverartarchive.org*' => Http::response('not found', 404),
        '*' => Http::response('boom', 500),
    ]);

    $res = app(MediaSearchService::class)->search('disco', 'random access memories');

    expect($res)->toHaveCount(1);
    expect($res[0]->title)->toBe('Random Access Memories');
    expect($res[0]->creator)->toBe('Daft Punk');
    expect($res[0]->year)->toBe(2013);
    expect($res[0]->cover_url)->toBeNull();
    expect($res[0]->source)->toBe('musicbrainz');
    expect($res[0]->external_id)->toBe('abc-123');
});

test('media search de disco usa la portada del cover art archive cuando responde', function () {
    Http::fake([
        '*musicbrainz.org/ws/2/release-group*' => Http::response([
            'release-groups' => [[
                'id' => 'abc-123',
                'title' => 'Discovery',
                'first-release-date' => '2001-03-12',
                'artist-credit' => [['name' => 'Daft Punk']],
            ]],
        ], 200),
        '*coverartarchive.org*' => Http::response('', 307),
        '*' => Http::response('boom', 500),
    ]);

    $res = app(MediaSearchService::class)->search('disco', 'discovery');

    expect($res)->toHaveCount(1);
    expect($res[0]->cover_url)->toBe('https://coverartarchive.org/release-group/abc-123/front-250');
});

test('queue search devuelve resultados normalizados como json', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'openlibrary.org/*' => Http::response([
            'docs' => [[
                'title' => 'Dune',
                'author_name' => ['Frank Herbert'],
                'first_publish_year' => 1965,
                'cover_i' => 123,
                'key' => '/works/OL1W',
            ]],
        ], 200),
        '*' => Http::response('boom', 500),
    ]);

    $json = $this->getJson('/today/queue/search?type=libro&q=dune')->assertOk()->json();

    expect($json)->toHaveCount(1);
    expect($json[0])->toBe([
        'title' => 'Dune',
        'creator' => 'Frank Herbert',
        'year' => 1965,
        'cover_url' => 'https://covers.openlibrary.org/b/id/123-M.jpg',
        'source' => 'openlibrary',
        'external_id' => '/works/OL1W',
    ]);
});

test('queue search rechaza tipos fuera de los permitidos', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Http::fake(['*' => Http::response('boom', 500)]);

    $this->getJson('/today/queue/search?type=musica&q=dune')->assertUnprocessable();

    expect(QueueItem::TYPES)->not->toContain('musica');
});

test('agregar a la cola deduplica por external_id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $body = ['title' => 'Dune', 'type' => 'libro', 'source' => 'openlibrary', 'external_id' => 'OL1W'];

    $this->post('/today/queue', $body)->assertRedirect();
    $this->post('/today/queue', $body)->assertRedirect();

    expect(QueueItem::where('user_id', $user->id)->count())->toBe(1);
});

test('agregar a la cola guarda todos los metadatos del resultado', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/today/queue', [
        'title' => 'Dune',
        'type' => 'libro',
        'source' => 'openlibrary',
        'external_id' => '/works/OL1W',
        'cover_url' => 'https://covers.openlibrary.org/b/id/123-M.jpg',
        'year' => 1965,
        'creator' => 'Frank Herbert',
    ])->assertRedirect();

    $item = QueueItem::where('user_id', $user->id)->firstOrFail();
    expect($item->title)->toBe('Dune');
    expect($item->creator)->toBe('Frank Herbert');
    expect($item->year)->toBe(1965);
    expect($item->cover_url)->toBe('https://covers.openlibrary.org/b/id/123-M.jpg');
    expect($item->source)->toBe('openlibrary');
});

test('agregar a la cola rechaza tipos invalidos', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson('/today/queue', ['title' => 'Dune', 'type' => 'musica'])->assertUnprocessable();

    expect(QueueItem::count())->toBe(0);
});

/**
 * Helper: claim de Wikidata con datavalue.
 */
function claimValue(mixed $value): array
{
    return ['mainsnak' => ['snaktype' => 'value', 'datavalue' => ['value' => $value]]];
}
