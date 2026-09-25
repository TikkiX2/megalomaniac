<?php

use App\Integrations\Connectors\Telegram\TelegramConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function telegram(): Connection
{
    return Connection::factory()->make([
        'kind' => 'telegram',
        'base_url' => 'https://api.telegram.org',
        'credentials' => ['token' => 'bot_token'],
        'options' => ['chat_id' => '12345'],
    ]);
}

it('declares the telegram catalog', function () {
    $keys = collect((new TelegramConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('bot.info', 'message.send', 'message.send_photo', 'updates.get', 'webhook.set')
        ->and((new TelegramConnector)->group())->toBe('Comunicación')
        ->and((new TelegramConnector)->defaultBaseUrl())->toBe('https://api.telegram.org');
});

it('gets bot info with the token in the path', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'mega_bot']], 200)]);

    $result = (new TelegramConnector)->execute(telegram(), 'bot.info', []);

    expect($result->ok)->toBeTrue()->and($result->data['result']['username'])->toBe('mega_bot');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'botbot_token/getMe'));
});

it('sends a message to the default chat', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);

    $result = (new TelegramConnector)->execute(telegram(), 'message.send', ['text' => 'Hola']);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && $request->data()['chat_id'] === '12345'
        && $request->data()['text'] === 'Hola');
});

it('maps telegram errors', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

    $result = (new TelegramConnector)->execute(telegram(), 'bot.info', []);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('401');
});
