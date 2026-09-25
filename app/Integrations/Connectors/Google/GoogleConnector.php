<?php

namespace App\Integrations\Connectors\Google;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\OAuth\OAuthBroker;
use App\Integrations\Transports\HttpCall;
use App\Models\Connection;

class GoogleConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Workspace';
    }

    public function group(): string
    {
        return 'Google';
    }

    public function description(): string
    {
        return 'Drive, Gmail y Calendar con OAuth.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://www.googleapis.com';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'oauth',
                type: 'oauth',
                label: 'Conectar con Google',
                help: 'Se abre el consentimiento de Google (Drive, Gmail y Calendar).',
            ),
        ];
    }

    public function actions(): array
    {
        $fileId = fn (): Param => new Param('file_id', 'string', true, 'ID del archivo en Drive');
        $calendarId = fn (): Param => new Param('calendar_id', 'string', false, 'ID del calendario', default: 'primary');
        $eventId = fn (): Param => new Param('event_id', 'string', true, 'ID del evento');

        return [
            new Action('drive.files.list', 'Listar archivos de Drive', 'Lista o busca archivos en Drive', ActionAccess::Read, [
                new Param('q', 'string', false, 'Consulta de Drive (sintaxis de la API)'),
                new Param('page_size', 'integer', false, 'Resultados por página', default: 25),
            ]),
            new Action('drive.files.get', 'Ver archivo de Drive', 'Obtiene metadata de un archivo', ActionAccess::Read, [$fileId()]),
            new Action('drive.files.create', 'Crear archivo en Drive', 'Sube un archivo de texto', ActionAccess::Write, [
                new Param('name', 'string', true, 'Nombre del archivo'),
                new Param('content', 'string', true, 'Contenido'),
                new Param('mime_type', 'string', false, 'MIME type', default: 'text/plain'),
            ]),
            new Action('drive.files.move', 'Mover archivo de Drive', 'Mueve un archivo a otra carpeta', ActionAccess::Write, [
                $fileId(),
                new Param('add_parents', 'string', true, 'ID de la carpeta destino'),
                new Param('remove_parents', 'string', false, 'ID de la carpeta a quitar'),
            ]),
            new Action('drive.files.delete', 'Borrar archivo de Drive', 'Manda un archivo a la papelera', ActionAccess::Destructive, [$fileId()]),

            new Action('gmail.messages.list', 'Listar mails', 'Lista o busca mensajes de Gmail', ActionAccess::Read, [
                new Param('q', 'string', false, 'Consulta de búsqueda de Gmail'),
                new Param('max_results', 'integer', false, 'Cantidad de resultados', default: 20),
            ]),
            new Action('gmail.messages.get', 'Ver mail', 'Obtiene un mensaje', ActionAccess::Read, [
                new Param('id', 'string', true, 'ID del mensaje'),
                new Param('format', 'string', false, 'Formato', enum: ['full', 'metadata', 'minimal'], default: 'full'),
            ]),
            new Action('gmail.messages.send', 'Enviar mail', 'Envía un mail', ActionAccess::Write, [
                new Param('to', 'string', true, 'Destinatario'),
                new Param('subject', 'string', true, 'Asunto'),
                new Param('body', 'string', true, 'Cuerpo'),
            ]),
            new Action('gmail.labels.list', 'Listar etiquetas', 'Lista etiquetas de Gmail', ActionAccess::Read),

            new Action('calendar.events.list', 'Listar eventos', 'Lista eventos del calendario', ActionAccess::Read, [
                $calendarId(),
                new Param('time_min', 'string', false, 'Desde (ISO 8601)'),
                new Param('max_results', 'integer', false, 'Cantidad de resultados', default: 20),
            ]),
            new Action('calendar.events.create', 'Crear evento', 'Crea un evento', ActionAccess::Write, [
                $calendarId(),
                new Param('summary', 'string', true, 'Título'),
                new Param('start', 'string', true, 'Inicio (ISO 8601)'),
                new Param('end', 'string', true, 'Fin (ISO 8601)'),
                new Param('description', 'string', false, 'Descripción'),
            ]),
            new Action('calendar.events.update', 'Actualizar evento', 'Actualiza un evento', ActionAccess::Write, [
                $calendarId(), $eventId(),
                new Param('summary', 'string', false, 'Título'),
                new Param('start', 'string', false, 'Inicio (ISO 8601)'),
                new Param('end', 'string', false, 'Fin (ISO 8601)'),
                new Param('description', 'string', false, 'Descripción'),
            ]),
            new Action('calendar.calendars.list', 'Listar calendarios', 'Lista calendarios del usuario', ActionAccess::Read),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $connection = app(OAuthBroker::class)->refreshIfNeeded($connection);

        $calendarId = $params['calendar_id'] ?? 'primary';

        return match ($key) {
            'drive.files.list' => $this->result(
                $this->request($connection, new HttpCall('GET', 'drive/v3/files', query: array_filter([
                    'q' => $params['q'] ?? null,
                    'pageSize' => $params['page_size'] ?? 25,
                    'fields' => 'files(id,name,mimeType,modifiedTime,size,parents),nextPageToken',
                ], fn (mixed $value): bool => $value !== null))),
                'Archivos listados.',
            ),
            'drive.files.get' => $this->result(
                $this->request($connection, new HttpCall('GET', "drive/v3/files/{$params['file_id']}", query: [
                    'fields' => 'id,name,mimeType,modifiedTime,size,parents,webViewLink',
                ])),
                'Archivo obtenido.',
            ),
            'drive.files.create' => $this->uploadFile($connection, $params),
            'drive.files.move' => $this->result(
                $this->request($connection, new HttpCall('PATCH', "drive/v3/files/{$params['file_id']}", query: array_filter([
                    'addParents' => $params['add_parents'],
                    'removeParents' => $params['remove_parents'] ?? null,
                ], fn (mixed $value): bool => $value !== null))),
                'Archivo movido.',
            ),
            'drive.files.delete' => $this->result(
                $this->request($connection, new HttpCall('DELETE', "drive/v3/files/{$params['file_id']}")),
                'Archivo borrado.',
            ),

            'gmail.messages.list' => $this->result(
                $this->request($connection, new HttpCall(
                    'GET',
                    'https://gmail.googleapis.com/gmail/v1/users/me/messages',
                    query: array_filter([
                        'q' => $params['q'] ?? null,
                        'maxResults' => $params['max_results'] ?? 20,
                    ], fn (mixed $value): bool => $value !== null),
                )),
                'Mensajes listados.',
            ),
            'gmail.messages.get' => $this->result(
                $this->request($connection, new HttpCall(
                    'GET',
                    "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$params['id']}",
                    query: ['format' => $params['format'] ?? 'full'],
                )),
                'Mensaje obtenido.',
            ),
            'gmail.messages.send' => $this->result(
                $this->request($connection, new HttpCall(
                    'POST',
                    'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
                    json: ['raw' => $this->encodeMessage($params['to'], $params['subject'], $params['body'])],
                )),
                'Mail enviado.',
            ),
            'gmail.labels.list' => $this->result(
                $this->request($connection, new HttpCall('GET', 'https://gmail.googleapis.com/gmail/v1/users/me/labels')),
                'Etiquetas listadas.',
            ),

            'calendar.events.list' => $this->result(
                $this->request($connection, new HttpCall('GET', "calendar/v3/calendars/{$calendarId}/events", query: array_filter([
                    'timeMin' => $params['time_min'] ?? null,
                    'maxResults' => $params['max_results'] ?? 20,
                    'singleEvents' => true,
                    'orderBy' => 'startTime',
                ], fn (mixed $value): bool => $value !== null))),
                'Eventos listados.',
            ),
            'calendar.events.create' => $this->result(
                $this->request($connection, new HttpCall('POST', "calendar/v3/calendars/{$calendarId}/events", json: array_filter([
                    'summary' => $params['summary'],
                    'description' => $params['description'] ?? null,
                    'start' => ['dateTime' => $params['start']],
                    'end' => ['dateTime' => $params['end']],
                ], fn (mixed $value): bool => $value !== null))),
                'Evento creado.',
            ),
            'calendar.events.update' => $this->result(
                $this->request($connection, new HttpCall('PATCH', "calendar/v3/calendars/{$calendarId}/events/{$params['event_id']}", json: array_filter([
                    'summary' => $params['summary'] ?? null,
                    'description' => $params['description'] ?? null,
                    'start' => isset($params['start']) ? ['dateTime' => $params['start']] : null,
                    'end' => isset($params['end']) ? ['dateTime' => $params['end']] : null,
                ], fn (mixed $value): bool => $value !== null))),
                'Evento actualizado.',
            ),
            'calendar.calendars.list' => $this->result(
                $this->request($connection, new HttpCall('GET', 'calendar/v3/users/me/calendarList')),
                'Calendarios listados.',
            ),

            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $connection = app(OAuthBroker::class)->refreshIfNeeded($connection);

        $response = $this->request($connection, new HttpCall('GET', 'drive/v3/about', query: ['fields' => 'user']));

        return $response->ok
            ? ConnectionTestResult::ok('Google OK', ['email' => $response->data['user']['emailAddress'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Google no respondió correctamente.');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function uploadFile(Connection $connection, array $params): ActionResult
    {
        $mimeType = $params['mime_type'] ?? 'text/plain';
        $boundary = 'megalomaniac'.bin2hex(random_bytes(8));

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode(['name' => $params['name'], 'mimeType' => $mimeType])."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mimeType}\r\n\r\n"
            .$params['content']."\r\n"
            ."--{$boundary}--";

        return $this->result(
            $this->request($connection, new HttpCall(
                method: 'POST',
                path: 'upload/drive/v3/files',
                query: ['uploadType' => 'multipart', 'fields' => 'id,name,mimeType,webViewLink'],
                body: $body,
                contentType: "multipart/related; boundary={$boundary}",
            )),
            'Archivo subido.',
        );
    }

    protected function encodeMessage(string $to, string $subject, string $body): string
    {
        $raw = "To: {$to}\r\n"
            ."Subject: {$subject}\r\n"
            ."MIME-Version: 1.0\r\n"
            ."Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            .$body;

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
