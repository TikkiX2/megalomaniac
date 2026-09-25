<?php

namespace App\Integrations\Connectors\Telegram;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Models\Connection;

class TelegramConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function group(): string
    {
        return 'Comunicación';
    }

    public function description(): string
    {
        return 'Bot de Telegram: enviar mensajes, recibir updates y webhooks.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://api.telegram.org';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token',
                type: 'password',
                label: 'Bot token',
                help: 'Te lo da @BotFather. En opciones podés fijar un chat_id por defecto.',
            ),
        ];
    }

    public function actions(): array
    {
        $chatId = fn (): Param => new Param('chat_id', 'string', false, 'Chat ID (default: el de la conexión)');

        return [
            new Action('bot.info', 'Ver bot', 'Información del bot (getMe)', ActionAccess::Read),
            new Action('message.send', 'Enviar mensaje', 'Envía un mensaje de texto', ActionAccess::Write, [
                $chatId(),
                new Param('text', 'string', true, 'Texto'),
                new Param('parse_mode', 'string', false, 'Formato', enum: ['Markdown', 'MarkdownV2', 'HTML']),
            ]),
            new Action('message.send_photo', 'Enviar foto', 'Envía una foto por URL', ActionAccess::Write, [
                $chatId(),
                new Param('photo', 'string', true, 'URL de la imagen'),
                new Param('caption', 'string', false, 'Leyenda'),
            ]),
            new Action('chat.info', 'Ver chat', 'Información de un chat', ActionAccess::Read, [$chatId()]),
            new Action('updates.get', 'Ver updates', 'Actualizaciones pendientes del bot', ActionAccess::Read, [
                new Param('offset', 'integer', false, 'Offset'),
                new Param('limit', 'integer', false, 'Cantidad de resultados', default: 20),
            ]),
            new Action('webhook.set', 'Configurar webhook', 'Setea el webhook del bot', ActionAccess::Write, [
                new Param('url', 'string', true, 'URL HTTPS del webhook'),
            ]),
            new Action('webhook.delete', 'Borrar webhook', 'Elimina el webhook del bot', ActionAccess::Write),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $chatId = (string) ($params['chat_id'] ?? $connection->options['chat_id'] ?? '');

        return match ($key) {
            'bot.info' => $this->result($this->api($connection, 'GET', 'getMe'), 'Bot obtenido.'),
            'message.send' => $this->result(
                $this->sendMessage($connection, $chatId, $params),
                'Mensaje enviado.',
            ),
            'message.send_photo' => $this->sendPhoto($connection, $chatId, $params),
            'chat.info' => $this->result($this->api($connection, 'POST', 'getChat', ['chat_id' => $chatId]), 'Chat obtenido.'),
            'updates.get' => $this->result(
                $this->api($connection, 'POST', 'getUpdates', array_filter([
                    'offset' => $params['offset'] ?? null,
                    'limit' => $params['limit'] ?? 20,
                ], fn (mixed $value): bool => $value !== null)),
                'Updates obtenidos.',
            ),
            'webhook.set' => $this->result(
                $this->api($connection, 'POST', 'setWebhook', ['url' => $params['url']]),
                'Webhook configurado.',
            ),
            'webhook.delete' => $this->result($this->api($connection, 'POST', 'deleteWebhook'), 'Webhook eliminado.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'getMe');

        return $response->ok
            ? ConnectionTestResult::ok('Telegram OK', ['username' => $response->data['result']['username'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Telegram no respondió.');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function sendMessage(Connection $connection, string $chatId, array $params): HttpResult
    {
        return $this->api($connection, 'POST', 'sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $params['text'],
            'parse_mode' => $params['parse_mode'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function sendPhoto(Connection $connection, string $chatId, array $params): ActionResult
    {
        return $this->result(
            $this->api($connection, 'POST', 'sendPhoto', array_filter([
                'chat_id' => $chatId,
                'photo' => $params['photo'],
                'caption' => $params['caption'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '')),
            'Foto enviada.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function api(Connection $connection, string $method, string $path, array $payload = []): HttpResult
    {
        $token = (string) ($connection->credentials['token'] ?? '');

        return $this->request($connection, new HttpCall(
            method: $method,
            path: "bot{$token}/{$path}",
            json: $method === 'POST' ? $payload : null,
            query: $method === 'GET' ? $payload : [],
        ));
    }
}
