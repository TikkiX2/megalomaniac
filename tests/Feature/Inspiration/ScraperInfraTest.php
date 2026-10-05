<?php

declare(strict_types=1);

use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\Scraping\HtmlParser;
use App\Inspiration\Scraping\ScraperClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Read a raw HTML fixture; scrapers never see the files directly.
 */
function scraperFixture(string $name): string
{
    $contents = file_get_contents(base_path('tests/Fixtures/Inspiration/html/'.$name.'.html'));

    if ($contents === false) {
        throw new RuntimeException("Missing scraper fixture [{$name}].");
    }

    return $contents;
}

/**
 * Run a callback and hand back the SourceException it threw, if any.
 */
function scraperException(callable $callback): ?SourceException
{
    try {
        $callback();
    } catch (SourceException $exception) {
        return $exception;
    }

    return null;
}

it('returns the response body for a successful fetch', function () {
    Http::fake(['*' => Http::response('<html>ok</html>', 200)]);

    expect((new ScraperClient)->get('https://design.test/feed'))->toBe('<html>ok</html>');
});

it('sends the descriptive user agent and the tier 2 timeout', function () {
    $captured = [];

    Http::fake(function (Request $request, array $options) use (&$captured) {
        $captured = [
            'userAgent' => $request->header('User-Agent')[0] ?? null,
            'timeout' => $options['timeout'] ?? null,
        ];

        return Http::response('<html></html>', 200);
    });

    (new ScraperClient)->get('https://design.test/feed');

    expect($captured['userAgent'])->toBe('MegalomaniacInspiration/1.0 (+uso personal)')
        ->and($captured['timeout'])->toBe(config('inspiration.timeouts.tier2'));
});

it('raises a human-readable SourceException for a 5xx response', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $exception = scraperException(fn () => (new ScraperClient)->get('https://design.test/feed'));

    expect($exception)->toBeInstanceOf(SourceException::class)
        ->and($exception?->getMessage())->toBe('scraper: design.test responded 500');
});

it('raises a SourceException for a 429 rate limit', function () {
    Http::fake(['*' => Http::response('', 429)]);

    $exception = scraperException(fn () => (new ScraperClient)->get('https://savee.test/'));

    expect($exception)->toBeInstanceOf(SourceException::class)
        ->and($exception?->getMessage())->toBe('scraper: savee.test responded 429');
});

it('raises a SourceException for a 403 anti-bot response', function () {
    Http::fake(['*' => Http::response('', 403)]);

    $exception = scraperException(fn () => (new ScraperClient)->get('https://dribbble.test/shots'));

    expect($exception)->toBeInstanceOf(SourceException::class)
        ->and($exception?->getMessage())->toBe('scraper: dribbble.test responded 403');
});

it('wraps transport failures such as timeouts in a SourceException', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    $exception = scraperException(fn () => (new ScraperClient)->get('https://posterspy.test/'));

    expect($exception)->toBeInstanceOf(SourceException::class)
        ->and($exception?->getMessage())->toContain('posterspy.test')
        ->and($exception?->getMessage())->toContain('cURL error 28')
        ->and($exception?->getPrevious())->toBeInstanceOf(ConnectionException::class)
        ->and($attempts)->toBe(2);
});

it('retries the request once before returning a later success', function () {
    Http::fake([
        '*' => Http::sequence()
            ->pushStatus(500)
            ->push('<html>recovered</html>', 200),
    ]);

    expect((new ScraperClient)->get('https://design.test/feed'))->toBe('<html>recovered</html>');

    // Two total attempts: the initial 500 then the successful retry.
    Http::assertSentCount(2);
});

it('raises a SourceException when the retry also fails', function () {
    Http::fake([
        '*' => Http::sequence()
            ->pushStatus(500)
            ->pushStatus(500),
    ]);

    $exception = scraperException(fn () => (new ScraperClient)->get('https://design.test/feed'));

    expect($exception)->toBeInstanceOf(SourceException::class)
        ->and($exception?->getMessage())->toBe('scraper: design.test responded 500');

    Http::assertSentCount(2);
});

it('extracts absolute card URLs with srcset, data-src and ancestor fallbacks', function () {
    $selectors = [
        'card' => ['.does-not-exist', '.card'],
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => '.card-title',
        'base' => 'https://example.test/gallery/',
    ];

    $cards = (new HtmlParser)->cards(scraperFixture('simple'), $selectors);

    expect($cards)->toHaveCount(3)
        ->and($cards[0])->toBe([
            'imageUrl' => 'https://cdn.example.com/1.jpg',
            'pageUrl' => 'https://example.test/work/one',
            'title' => 'First Work',
        ])
        ->and($cards[1])->toBe([
            'imageUrl' => 'https://cdn.example.com/2.jpg',
            'pageUrl' => 'https://elsewhere.test/two',
            'title' => 'Second Work',
        ])
        ->and($cards[2])->toBe([
            'imageUrl' => 'https://cdn.example.com/3.jpg',
            'pageUrl' => 'https://example.test/work/three',
            'title' => 'Third Work',
        ]);
});

it('prefers a lazy-load URL over a data URI placeholder', function () {
    $html = '<div class="card"><a class="card-link" href="/p/5">'
        .'<img src="data:image/gif;base64,R0lGOD" data-src="//cdn.example.com/5.jpg"></a></div>';

    $cards = (new HtmlParser)->cards($html, [
        'card' => '.card',
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => null,
        'base' => 'https://example.test/',
    ]);

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['imageUrl'])->toBe('https://cdn.example.com/5.jpg')
        ->and($cards[0]['pageUrl'])->toBe('https://example.test/p/5');
});

it('falls back through an array of title selectors', function () {
    $html = '<div class="card"><a class="card-link" href="/p/6"><img src="/6.jpg"></a>'
        .'<figcaption class="caption">Caption Title</figcaption></div>';

    $cards = (new HtmlParser)->cards($html, [
        'card' => '.card',
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => ['.title', '.caption'],
        'base' => 'https://example.test/',
    ]);

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['title'])->toBe('Caption Title');
});

it('keeps only absolute URLs when neither base selector nor base tag is present', function () {
    $cards = (new HtmlParser)->cards(scraperFixture('nobase'), [
        'card' => '.card',
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => null,
    ]);

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['imageUrl'])->toBe('https://cdn.test/1.jpg')
        ->and($cards[0]['pageUrl'])->toBe('https://absolute.test/p/1');
});

it('returns an empty array when a card selector is invalid CSS', function () {
    $cards = (new HtmlParser)->cards(scraperFixture('simple'), [
        'card' => ['!!!not a selector!!!'],
        'image' => 'img',
        'link' => 'a',
        'title' => null,
        'base' => 'https://example.test/',
    ]);

    expect($cards)->toBe([]);
});

it('resolves relative URLs against a base tag when no base selector is passed', function () {
    $html = '<base href="https://based.test/root/">'
        .'<div class="card"><a class="card-link" href="p/9"><img src="img/9.jpg"></a></div>';

    $cards = (new HtmlParser)->cards($html, [
        'card' => '.card',
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => null,
    ]);

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['imageUrl'])->toBe('https://based.test/root/img/9.jpg')
        ->and($cards[0]['pageUrl'])->toBe('https://based.test/root/p/9')
        ->and($cards[0]['title'])->toBeNull();
});

it('drops cards whose image or page URL is not an absolute http(s) URL', function () {
    $html = '<div class="card"><a class="card-link" href="javascript:void(0)">'
        .'<img src="data:image/png;base64,AAAA"></a></div>'
        .'<div class="card"><img src="/only-image.jpg"></div>';

    $cards = (new HtmlParser)->cards($html, [
        'card' => '.card',
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => null,
        'base' => 'https://example.test/',
    ]);

    expect($cards)->toBe([]);
});

it('returns an empty array for broken markup without throwing', function () {
    $cards = (new HtmlParser)->cards(scraperFixture('broken'), [
        'card' => '.card',
        'image' => 'img',
        'link' => 'a.card-link',
        'title' => '.card-title',
        'base' => 'https://example.test/',
    ]);

    expect($cards)->toBe([]);
});

it('returns an empty array when the card selectors match nothing', function () {
    $cards = (new HtmlParser)->cards(scraperFixture('simple'), [
        'card' => ['.nope', '.still-nope'],
        'image' => 'img',
        'link' => 'a',
        'title' => null,
        'base' => 'https://example.test/',
    ]);

    expect($cards)->toBe([]);
});
