<?php

use App\Integrations\Connectors\Google\GoogleConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function google(): Connection
{
    return Connection::factory()->make([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'base_url' => 'https://www.googleapis.com',
        'credentials' => [
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ]);
}

it('declares the google catalog', function () {
    $keys = collect((new GoogleConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('drive.files.list', 'drive.files.create', 'gmail.messages.send', 'calendar.events.list')
        ->and((new GoogleConnector)->group())->toBe('Google');
});

it('lists drive files', function () {
    Http::fake(['www.googleapis.com/drive/v3/files*' => Http::response(['files' => [['id' => 'f1']]], 200)]);

    $result = (new GoogleConnector)->execute(google(), 'drive.files.list', ['q' => "name contains 'x'"]);

    expect($result->ok)->toBeTrue()->and($result->data['files'][0]['id'])->toBe('f1');
});

it('uploads a drive file as multipart related', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'new-file'], 200)]);

    $result = (new GoogleConnector)->execute(google(), 'drive.files.create', [
        'name' => 'nota.txt',
        'content' => 'hola mundo',
    ]);

    expect($result->ok)->toBeTrue()->and($result->data['id'])->toBe('new-file');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'uploadType=multipart')
        && str_contains($request->body(), 'nota.txt')
        && str_contains($request->body(), 'hola mundo'));
});

it('sends a gmail message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'], 200)]);

    $result = (new GoogleConnector)->execute(google(), 'gmail.messages.send', [
        'to' => 'a@b.com',
        'subject' => 'Hola',
        'body' => 'Texto',
    ]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail/v1/users/me/messages/send')
        && $request['raw'] !== null);
});

it('lists calendar events with the primary calendar by default', function () {
    Http::fake(['www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => [['id' => 'e1']]], 200)]);

    $result = (new GoogleConnector)->execute(google(), 'calendar.events.list', []);

    expect($result->ok)->toBeTrue()->and($result->data['items'][0]['id'])->toBe('e1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'calendar/v3/calendars/primary/events'));
});

it('refreshes the token before requests when expired', function () {
    config(['services.google.oauth.client_id' => 'c', 'services.google.oauth.client_secret' => 's']);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600]),
        'www.googleapis.com/*' => Http::response(['files' => []], 200),
    ]);

    $connection = Connection::factory()->create([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'base_url' => 'https://www.googleapis.com',
        'credentials' => [
            'access_token' => 'old',
            'refresh_token' => 'rt',
            'expires_at' => now()->subMinute()->toIso8601String(),
        ],
    ]);

    (new GoogleConnector)->execute($connection, 'drive.files.list', []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer new'));
});

it('tests the connection against the drive about endpoint', function () {
    Http::fake(['www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['emailAddress' => 'me@x.com']], 200)]);

    $result = (new GoogleConnector)->test(google());

    expect($result->ok)->toBeTrue()->and($result->meta['email'])->toBe('me@x.com');
});
