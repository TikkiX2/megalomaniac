<?php

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Enums\TransportKind;
use App\Integrations\Transports\DirectTransport;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\LocalSocketTransport;
use App\Integrations\Transports\TransportFactory;
use App\Models\Connection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends bearer token requests from credentials', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

    $connection = Connection::factory()->make([
        'kind' => 'github',
        'base_url' => 'https://api.example.com',
        'transport' => TransportKind::Direct,
        'credentials' => ['token' => 'tok_123'],
    ]);

    $result = (new DirectTransport)->request($connection, new HttpCall('GET', 'repos'));

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer tok_123')
        && $request->url() === 'https://api.example.com/repos');
});

it('sends basic auth when auth type is basic', function () {
    Http::fake(['api.example.com/*' => Http::response([], 200)]);

    $connection = Connection::factory()->make([
        'base_url' => 'https://api.example.com',
        'auth_type' => 'basic',
        'credentials' => ['username' => 'u', 'password' => 'p'],
    ]);

    (new DirectTransport)->request($connection, new HttpCall('GET', 'x'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('u:p')));
});

it('maps http errors to a failed result', function () {
    Http::fake(['api.example.com/*' => Http::response(['message' => 'nope'], 403)]);

    $connection = Connection::factory()->make(['base_url' => 'https://api.example.com']);
    $result = (new DirectTransport)->request($connection, new HttpCall('GET', 'x'));

    expect($result->ok)->toBeFalse()->and($result->status)->toBe(403)
        ->and($result->error)->toContain('403');
});

it('sends raw bodies when provided', function () {
    Http::fake(['api.example.com/*' => Http::response([], 200)]);

    $connection = Connection::factory()->make(['base_url' => 'https://api.example.com']);

    (new DirectTransport)->request($connection, new HttpCall(
        method: 'POST',
        path: 'upload',
        body: 'plain-text-payload',
        contentType: 'text/plain',
    ));

    Http::assertSent(fn (Request $request) => $request->body() === 'plain-text-payload');
});

it('refuses exec on http transports', function () {
    $connection = Connection::factory()->make();

    (new DirectTransport)->exec($connection, 'ls');
})->throws(UnsupportedTransportException::class);

it('exposes unix socket curl options for local socket transport', function () {
    $connection = Connection::factory()->make([
        'transport' => TransportKind::LocalSocket,
        'transport_config' => ['socket_path' => '/var/run/docker.sock'],
    ]);

    $options = (new LocalSocketTransport)->curlOptions($connection);

    expect($options[CURLOPT_UNIX_SOCKET_PATH])->toBe('/var/run/docker.sock');
});

it('maps connection failures to a friendly message', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

    $connection = Connection::factory()->make(['base_url' => 'http://127.0.0.1:9']);
    $result = (new DirectTransport)->request($connection, new HttpCall('GET', 'x'));

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('No se pudo conectar');
});

it('honors a per-connection verify=false option', function () {
    $connection = Connection::factory()->make(['options' => ['verify' => false]]);

    expect((new DirectTransport)->clientOptions($connection))->toBe(['verify' => false]);
});

it('verifies tls by default', function () {
    $connection = Connection::factory()->make(['options' => null]);

    expect((new DirectTransport)->clientOptions($connection))->toBe([]);
});

it('resolves the transport from the connection', function () {
    expect(TransportFactory::make(Connection::factory()->make(['transport' => TransportKind::Direct])))
        ->toBeInstanceOf(DirectTransport::class);

    expect(TransportFactory::make(Connection::factory()->make(['transport' => TransportKind::LocalSocket])))
        ->toBeInstanceOf(LocalSocketTransport::class);
});
