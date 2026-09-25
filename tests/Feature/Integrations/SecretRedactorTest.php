<?php

use App\Integrations\Actions\Param;
use App\Integrations\Support\SecretRedactor;

it('redacts sensitive params by key', function () {
    $params = [new Param('token', 'string', true, 'Token', sensitive: true)];

    $redacted = SecretRedactor::redact([
        'token' => 'abc123',
        'title' => 'Hola',
    ], $params);

    expect($redacted['token'])->toBe('[redacted]')
        ->and($redacted['title'])->toBe('Hola');
});

it('redacts nested keys that look secret', function () {
    $redacted = SecretRedactor::redact([
        'auth' => ['password' => 'p', 'api_key' => 'k'],
        'list' => [['access_token' => 't'], ['name' => 'ok']],
    ]);

    expect($redacted['auth']['password'])->toBe('[redacted]')
        ->and($redacted['auth']['api_key'])->toBe('[redacted]')
        ->and($redacted['list'][0]['access_token'])->toBe('[redacted]')
        ->and($redacted['list'][1]['name'])->toBe('ok');
});

it('scrubs secret patterns from strings', function () {
    $value = SecretRedactor::redactString('Authorization: Bearer sk-abcdef123456');

    expect($value)->not->toContain('sk-abcdef123456')
        ->and($value)->toContain('[redacted]');
});
