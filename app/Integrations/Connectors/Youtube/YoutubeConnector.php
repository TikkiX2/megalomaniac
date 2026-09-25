<?php

namespace App\Integrations\Connectors\Youtube;

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

class YoutubeConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function group(): string
    {
        return 'Contenido';
    }

    public function description(): string
    {
        return 'Búsqueda, canales, videos y playlists (Data API v3).';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://www.googleapis.com';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'api_key',
                type: 'password',
                label: 'API Key',
                help: 'Google Cloud Console → YouTube Data API v3 → credenciales → API key.',
            ),
        ];
    }

    public function actions(): array
    {
        return [
            new Action('videos.search', 'Buscar videos', 'Busca videos por texto o canal', ActionAccess::Read, [
                new Param('query', 'string', true, 'Texto de búsqueda'),
                new Param('max_results', 'integer', false, 'Resultados', default: 10),
                new Param('channel_id', 'string', false, 'Limitar a un canal'),
                new Param('order', 'string', false, 'Orden', enum: ['relevance', 'date', 'viewCount'], default: 'relevance'),
            ]),
            new Action('videos.get', 'Ver videos', 'Detalle y estadísticas de videos', ActionAccess::Read, [
                new Param('video_ids', 'string', true, 'IDs separados por coma'),
            ]),
            new Action('channels.get', 'Ver canal', 'Detalle de canales (incluye uploads playlist)', ActionAccess::Read, [
                new Param('channel_id', 'string', false, 'ID del canal'),
                new Param('for_handle', 'string', false, 'Handle, p. ej. @laravelphp'),
            ]),
            new Action('playlist.items', 'Ver playlist', 'Items de una playlist (uploads de un canal)', ActionAccess::Read, [
                new Param('playlist_id', 'string', true, 'ID de la playlist'),
                new Param('max_results', 'integer', false, 'Resultados', default: 20),
            ]),
            new Action('videos.categories', 'Ver categorías', 'Categorías de videos por región', ActionAccess::Read, [
                new Param('region_code', 'string', false, 'Región', default: 'US'),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'videos.search' => $this->result(
                $this->api($connection, 'youtube/v3/search', array_filter([
                    'part' => 'snippet',
                    'type' => 'video',
                    'q' => $params['query'],
                    'maxResults' => $params['max_results'] ?? 10,
                    'channelId' => $params['channel_id'] ?? null,
                    'order' => $params['order'] ?? 'relevance',
                ], fn (mixed $value): bool => $value !== null && $value !== '')),
                'Búsqueda completada.',
            ),
            'videos.get' => $this->result(
                $this->api($connection, 'youtube/v3/videos', [
                    'part' => 'snippet,statistics,contentDetails',
                    'id' => $params['video_ids'],
                ]),
                'Videos obtenidos.',
            ),
            'channels.get' => $this->result(
                $this->api($connection, 'youtube/v3/channels', array_filter([
                    'part' => 'snippet,statistics,contentDetails',
                    'id' => $params['channel_id'] ?? null,
                    'forHandle' => $params['for_handle'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== '')),
                'Canal obtenido.',
            ),
            'playlist.items' => $this->result(
                $this->api($connection, 'youtube/v3/playlistItems', [
                    'part' => 'snippet,contentDetails',
                    'playlistId' => $params['playlist_id'],
                    'maxResults' => $params['max_results'] ?? 20,
                ]),
                'Playlist obtenida.',
            ),
            'videos.categories' => $this->result(
                $this->api($connection, 'youtube/v3/videoCategories', [
                    'part' => 'snippet',
                    'regionCode' => $params['region_code'] ?? 'US',
                ]),
                'Categorías obtenidas.',
            ),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'youtube/v3/videoCategories', [
            'part' => 'snippet',
            'regionCode' => 'US',
        ]);

        return $response->ok
            ? ConnectionTestResult::ok('YouTube OK')
            : ConnectionTestResult::fail($response->error ?? 'YouTube no respondió.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function api(Connection $connection, string $path, array $query = []): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: 'GET',
            path: $path,
            query: $query + ['key' => (string) ($connection->credentials['api_key'] ?? '')],
        ));
    }
}
