<?php

declare(strict_types=1);

use App\Inspiration\Support\UrlSafety;
use App\Rules\SecureHttpUrl;

/**
 * Run the rule and return the failure message, or null when it passes.
 *
 * @param  Closure(string): array<int, string>|null  $resolver
 */
function secureUrlFailure(string $url, ?Closure $resolver = null): ?string
{
    $failure = null;

    (new SecureHttpUrl($resolver))->validate('url', $url, function (string $message) use (&$failure): void {
        $failure = $message;
    });

    return $failure;
}

it('accepts public http and https urls', function (string $url): void {
    expect(secureUrlFailure($url))->toBeNull();
})->with([
    'https://93.184.216.34/image.jpg',
    'http://8.8.8.8:8080/image.png',
    'https://[2606:4700:4700::1111]/image.webp',
    'https://93.184.216.34:8443/a/b.jpg?token=1#frag',
]);

it('rejects non-http schemes and relative urls', function (string $url): void {
    expect(secureUrlFailure($url))->toBe('URL inválida');
})->with([
    'data:image/png;base64,AAAA',
    'file:///etc/passwd',
    'ftp://10.0.0.1/file.jpg',
    '//evil.com/image.jpg',
    'javascript:alert(1)',
    'not-a-url',
]);

it('rejects private and reserved ipv4 literals', function (string $url): void {
    expect(secureUrlFailure($url))->toBe('URL interna no permitida');
})->with([
    'http://127.0.0.1/x.jpg',
    'http://127.8.8.8/x.jpg',
    'http://10.1.2.3/x.jpg',
    'http://172.16.0.1/x.jpg',
    'http://172.31.255.255/x.jpg',
    'http://192.168.1.1/x.jpg',
    'http://169.254.169.254/latest/meta-data',
    'http://100.64.0.1/x.jpg',
    'http://0.0.0.0/x.jpg',
    'http://192.0.0.1/x.jpg',
    'http://198.18.0.1/x.jpg',
    'http://224.0.0.1/x.jpg',
    'http://240.0.0.1/x.jpg',
]);

it('accepts public ipv4 literals just outside the blocked ranges', function (string $url): void {
    expect(secureUrlFailure($url))->toBeNull();
})->with([
    'http://100.128.0.1/x.jpg',
    'http://172.32.0.1/x.jpg',
    'http://198.20.0.1/x.jpg',
]);

it('rejects private ipv6 literals including ipv4-mapped', function (string $url): void {
    expect(secureUrlFailure($url))->toBe('URL interna no permitida');
})->with([
    'http://[::1]/x.jpg',
    'http://[fc00::1]/x.jpg',
    'http://[fd12:3456::1]/x.jpg',
    'http://[fe80::1]/x.jpg',
    'http://[::ffff:127.0.0.1]/x.jpg',
    'http://[::ffff:10.0.0.1]/x.jpg',
]);

it('rejects reserved hostnames', function (string $url): void {
    expect(secureUrlFailure($url))->toBe('URL interna no permitida');
})->with([
    'http://localhost/x.jpg',
    'http://foo.local/x.jpg',
    'http://foo.internal/x.jpg',
    'http://foo.localhost/x.jpg',
]);

it('rejects hostnames resolving to private addresses', function (): void {
    expect(secureUrlFailure('https://evil.test/x.jpg', static fn (string $host): array => ['93.184.216.34', '10.0.0.5']))
        ->toBe('URL interna no permitida');
});

it('accepts hostnames resolving only to public addresses', function (): void {
    expect(secureUrlFailure('https://cdn.test/x.jpg', static fn (string $host): array => ['93.184.216.34']))
        ->toBeNull();
});

it('rejects unresolvable hostnames', function (): void {
    expect(secureUrlFailure('https://nope.test/x.jpg', static fn (string $host): array => []))
        ->toBe('URL interna no permitida');
});

it('guards redirect effective uris with the same ranges', function (): void {
    expect(UrlSafety::redirectTargetIsPrivate('http://127.0.0.1/evil.jpg'))->toBeTrue()
        ->and(UrlSafety::redirectTargetIsPrivate('http://169.254.169.254/latest/meta-data'))->toBeTrue()
        ->and(UrlSafety::redirectTargetIsPrivate('http://[::ffff:10.0.0.1]/x.jpg'))->toBeTrue()
        ->and(UrlSafety::redirectTargetIsPrivate('https://93.184.216.34/ok.jpg'))->toBeFalse()
        ->and(UrlSafety::redirectTargetIsPrivate(null))->toBeFalse();
});
