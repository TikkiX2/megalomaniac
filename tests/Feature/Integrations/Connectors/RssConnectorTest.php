<?php

use App\Integrations\Connectors\Rss\RssConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function rss(string $url = 'https://blog.test/feed.xml'): Connection
{
    return Connection::factory()->make([
        'kind' => 'rss',
        'base_url' => $url,
        'auth_type' => 'none',
        'credentials' => [],
    ]);
}

function rssFixture(): string
{
    return <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <title>Mi Blog</title>
      <description>Notas técnicas</description>
      <item>
        <title>Post 1</title>
        <link>https://blog.test/1</link>
        <description>Resumen 1</description>
        <pubDate>Mon, 01 Sep 2025 10:00:00 GMT</pubDate>
        <guid>post-1</guid>
      </item>
      <item><title>Post 2</title><link>https://blog.test/2</link></item>
    </channel></rss>
    XML;
}

it('declares the rss catalog', function () {
    $connector = new RssConnector;

    expect(collect($connector->actions())->pluck('key'))->toContain('feed.fetch', 'feed.info')
        ->and($connector->authFields())->toBe([])
        ->and($connector->defaultBaseUrl())->toBeNull();
});

it('fetches and normalizes rss items', function () {
    Http::fake(['blog.test/*' => Http::response(rssFixture(), 200)]);

    $result = (new RssConnector)->execute(rss(), 'feed.fetch', ['limit' => 10]);

    expect($result->ok)->toBeTrue()
        ->and($result->data['title'])->toBe('Mi Blog')
        ->and($result->data['items'])->toHaveCount(2)
        ->and($result->data['items'][0]['external_id'])->toBe('post-1')
        ->and($result->data['items'][0]['url'])->toBe('https://blog.test/1')
        ->and($result->data['items'][1]['external_id'])->toBe(sha1('https://blog.test/2'));
});

it('parses atom feeds', function () {
    $atom = <<<'XML'
    <?xml version="1.0" encoding="utf-8"?>
    <feed xmlns="http://www.w3.org/2005/Atom">
      <title>Atom Feed</title>
      <entry><title>A1</title><link href="https://blog.test/a1"/><summary>S1</summary><id>a1</id><updated>2025-09-01T10:00:00Z</updated></entry>
    </feed>
    XML;

    Http::fake(['blog.test/*' => Http::response($atom, 200)]);

    $result = (new RssConnector)->execute(rss(), 'feed.fetch', []);

    expect($result->ok)->toBeTrue()
        ->and($result->data['title'])->toBe('Atom Feed')
        ->and($result->data['items'][0]['external_id'])->toBe('a1')
        ->and($result->data['items'][0]['url'])->toBe('https://blog.test/a1');
});

it('requests the feed url without adding a trailing slash', function () {
    Http::fake(['news.ycombinator.com/*' => Http::response(rssFixture(), 200)]);

    $result = (new RssConnector)->execute(rss('https://news.ycombinator.com/rss'), 'feed.info', []);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://news.ycombinator.com/rss');
});

it('reports feed errors', function () {
    Http::fake(['blog.test/*' => Http::response('nope', 404)]);

    $result = (new RssConnector)->execute(rss(), 'feed.info', []);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('404');
});

it('tests the connection via feed info', function () {
    Http::fake(['blog.test/*' => Http::response(rssFixture(), 200)]);

    $result = (new RssConnector)->test(rss());

    expect($result->ok)->toBeTrue()->and($result->meta['title'])->toBe('Mi Blog');
});
