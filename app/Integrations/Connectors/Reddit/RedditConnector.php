<?php

namespace App\Integrations\Connectors\Reddit;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\OAuth\OAuthBroker;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\HttpResult;
use App\Models\Connection;

class RedditConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'reddit';
    }

    public function label(): string
    {
        return 'Reddit';
    }

    public function group(): string
    {
        return 'Contenido';
    }

    public function description(): string
    {
        return 'Subreddits, búsqueda, posts, comentarios y votos.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://oauth.reddit.com';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'client_id',
                type: 'text',
                label: 'Client ID',
                help: 'reddit.com/prefs/apps → crear app tipo "web app".',
            ),
            new AuthField(name: 'client_secret', type: 'password', label: 'Client secret'),
        ];
    }

    public function actions(): array
    {
        $sub = fn (): Param => new Param('sub', 'string', true, 'Subreddit (sin r/)');

        return [
            new Action('user.me', 'Ver usuario', 'Usuario autenticado', ActionAccess::Read),
            new Action('subreddit.hot', 'Hot de subreddit', 'Posts calientes', ActionAccess::Read, [
                $sub(),
                new Param('limit', 'integer', false, 'Resultados', default: 25),
            ]),
            new Action('subreddit.new', 'Nuevos de subreddit', 'Posts nuevos', ActionAccess::Read, [
                $sub(),
                new Param('limit', 'integer', false, 'Resultados', default: 25),
            ]),
            new Action('subreddit.top', 'Top de subreddit', 'Posts top', ActionAccess::Read, [
                $sub(),
                new Param('limit', 'integer', false, 'Resultados', default: 25),
                new Param('t', 'string', false, 'Período', enum: ['hour', 'day', 'week', 'month', 'year', 'all'], default: 'day'),
            ]),
            new Action('search.query', 'Buscar', 'Búsqueda de posts', ActionAccess::Read, [
                new Param('query', 'string', true, 'Texto'),
                new Param('subreddit', 'string', false, 'Limitar a subreddit'),
                new Param('sort', 'string', false, 'Orden', enum: ['relevance', 'hot', 'top', 'new'], default: 'relevance'),
                new Param('limit', 'integer', false, 'Resultados', default: 25),
            ]),
            new Action('post.get', 'Ver post', 'Post y comentarios', ActionAccess::Read, [
                new Param('id', 'string', true, 'ID (t3_…)'),
            ]),
            new Action('post.comments', 'Ver comentarios', 'Comentarios de un post', ActionAccess::Read, [
                new Param('id', 'string', true, 'ID (t3_…)'),
                new Param('limit', 'integer', false, 'Resultados', default: 50),
            ]),
            new Action('saved.list', 'Ver guardados', 'Posts guardados', ActionAccess::Read, [
                new Param('username', 'string', true, 'Usuario'),
                new Param('limit', 'integer', false, 'Resultados', default: 25),
            ]),
            new Action('post.submit', 'Publicar', 'Crea un post', ActionAccess::Write, [
                $sub(),
                new Param('title', 'string', true, 'Título'),
                new Param('kind', 'string', true, 'Tipo', enum: ['self', 'link']),
                new Param('text', 'string', false, 'Texto (self)'),
                new Param('url', 'string', false, 'URL (link)'),
            ]),
            new Action('comment.create', 'Comentar', 'Responde a un post o comentario', ActionAccess::Write, [
                new Param('parent_id', 'string', true, 'ID padre (t3_/t1_)'),
                new Param('text', 'string', true, 'Texto'),
            ]),
            new Action('vote', 'Votar', 'Vota un post o comentario', ActionAccess::Write, [
                new Param('id', 'string', true, 'ID (t3_/t1_)'),
                new Param('direction', 'integer', true, '1 upvote, -1 downvote, 0 quitar'),
            ]),
            new Action('save', 'Guardar', 'Guarda un post o comentario', ActionAccess::Write, [
                new Param('id', 'string', true, 'ID (t3_/t1_)'),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $connection = app(OAuthBroker::class)->refreshIfNeeded($connection);

        return match ($key) {
            'user.me' => $this->result($this->api($connection, 'GET', 'api/v1/me'), 'Usuario obtenido.'),
            'subreddit.hot' => $this->listing($connection, "r/{$params['sub']}/hot", ['limit' => $params['limit'] ?? 25], 'Posts listados.'),
            'subreddit.new' => $this->listing($connection, "r/{$params['sub']}/new", ['limit' => $params['limit'] ?? 25], 'Posts listados.'),
            'subreddit.top' => $this->listing($connection, "r/{$params['sub']}/top", [
                'limit' => $params['limit'] ?? 25,
                't' => $params['t'] ?? 'day',
            ], 'Posts listados.'),
            'search.query' => $this->listing($connection, 'search', array_filter([
                'q' => $params['query'],
                'restrict_sr' => isset($params['subreddit']) ? 'true' : null,
                'sort' => $params['sort'] ?? 'relevance',
            ], fn (mixed $value): bool => $value !== null), 'Búsqueda completada.', $params['subreddit'] ?? null),
            'post.get' => $this->result($this->api($connection, 'GET', "comments/{$params['id']}"), 'Post obtenido.'),
            'post.comments' => $this->result(
                $this->api($connection, 'GET', "comments/{$params['id']}", ['limit' => $params['limit'] ?? 50]),
                'Comentarios obtenidos.',
            ),
            'saved.list' => $this->result(
                $this->api($connection, 'GET', "user/{$params['username']}/saved", ['limit' => $params['limit'] ?? 25]),
                'Guardados listados.',
            ),
            'post.submit' => $this->form($connection, 'api/submit', array_filter([
                'api_type' => 'json',
                'kind' => $params['kind'],
                'sr' => $params['sub'],
                'title' => $params['title'],
                'text' => $params['text'] ?? null,
                'url' => $params['url'] ?? null,
            ], fn (mixed $value): bool => $value !== null), 'Post creado.'),
            'comment.create' => $this->form($connection, 'api/comment', [
                'api_type' => 'json',
                'parent' => $params['parent_id'],
                'text' => $params['text'],
            ], 'Comentario creado.'),
            'vote' => $this->form($connection, 'api/vote', [
                'id' => $params['id'],
                'dir' => (string) $params['direction'],
            ], 'Voto registrado.'),
            'save' => $this->form($connection, 'api/save', ['id' => $params['id']], 'Guardado.'),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->api($connection, 'GET', 'api/v1/me');

        return $response->ok
            ? ConnectionTestResult::ok('Reddit OK', ['name' => $response->data['name'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'Reddit no respondió.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function listing(Connection $connection, string $path, array $query, string $success, ?string $subreddit = null): ActionResult
    {
        if ($subreddit !== null) {
            $path = "r/{$subreddit}/".ltrim($path, '/');
        }

        return $this->result($this->api($connection, 'GET', $path, $query), $success);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function form(Connection $connection, string $path, array $fields, string $success): ActionResult
    {
        $response = $this->request($connection, new HttpCall(
            method: 'POST',
            path: $path,
            query: ['raw_json' => 1],
            body: http_build_query($fields),
            contentType: 'application/x-www-form-urlencoded',
            headers: $this->headers($connection),
        ));

        return $response->ok
            ? ActionResult::success($success, $response->data)
            : ActionResult::failure($response->error ?? 'La acción falló.');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function api(Connection $connection, string $method, string $path, array $query = []): HttpResult
    {
        return $this->request($connection, new HttpCall(
            method: $method,
            path: $path,
            query: $query + ['raw_json' => 1],
            headers: $this->headers($connection),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function headers(Connection $connection): array
    {
        return [
            'Authorization' => 'Bearer '.($connection->credentials['access_token'] ?? ''),
            'User-Agent' => 'megalomaniac/1.0',
        ];
    }
}
