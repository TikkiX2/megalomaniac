<?php

namespace App\Integrations\Connectors\Github;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Transports\HttpCall;
use App\Models\Connection;

class GithubConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
    }

    public function group(): string
    {
        return 'Dev';
    }

    public function description(): string
    {
        return 'Repositorios, issues, pull requests, Actions y notificaciones.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://api.github.com';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token',
                type: 'password',
                label: 'Personal access token',
                help: 'Fine-grained token con permisos de lectura/escritura en los repos que quieras operar. Para GitHub Enterprise, cambiá la base URL.',
            ),
        ];
    }

    public function actions(): array
    {
        $owner = fn (): Param => new Param('owner', 'string', true, 'Dueño del repositorio');
        $repo = fn (): Param => new Param('repo', 'string', true, 'Nombre del repositorio');
        $number = fn (string $what): Param => new Param('number', 'integer', true, "Número de {$what}");

        return [
            new Action('repos.list', 'Listar repositorios', 'Lista repositorios del usuario autenticado', ActionAccess::Read, [
                new Param('per_page', 'integer', false, 'Cantidad de resultados', default: 30),
            ]),
            new Action('repos.get', 'Ver repositorio', 'Obtiene un repositorio', ActionAccess::Read, [$owner(), $repo()]),

            new Action('issues.list', 'Listar issues', 'Lista issues del repositorio', ActionAccess::Read, [
                $owner(), $repo(),
                new Param('state', 'string', false, 'Estado', enum: ['open', 'closed', 'all'], default: 'open'),
                new Param('per_page', 'integer', false, 'Cantidad de resultados', default: 30),
            ]),
            new Action('issues.get', 'Ver issue', 'Obtiene un issue', ActionAccess::Read, [$owner(), $repo(), $number('issue')]),
            new Action('issues.create', 'Crear issue', 'Crea un issue', ActionAccess::Write, [
                $owner(), $repo(),
                new Param('title', 'string', true, 'Título'),
                new Param('body', 'string', false, 'Cuerpo'),
            ]),
            new Action('issues.comment', 'Comentar issue', 'Agrega un comentario a un issue', ActionAccess::Write, [
                $owner(), $repo(), $number('issue'),
                new Param('body', 'string', true, 'Comentario'),
            ]),
            new Action('issues.close', 'Cerrar issue', 'Cierra un issue', ActionAccess::Write, [$owner(), $repo(), $number('issue')]),

            new Action('pulls.list', 'Listar pull requests', 'Lista PRs del repositorio', ActionAccess::Read, [
                $owner(), $repo(),
                new Param('state', 'string', false, 'Estado', enum: ['open', 'closed', 'all'], default: 'open'),
            ]),
            new Action('pulls.get', 'Ver pull request', 'Obtiene un PR', ActionAccess::Read, [$owner(), $repo(), $number('PR')]),
            new Action('pulls.files', 'Ver archivos de un PR', 'Lista archivos modificados por un PR', ActionAccess::Read, [$owner(), $repo(), $number('PR')]),
            new Action('pulls.create', 'Crear pull request', 'Crea un PR', ActionAccess::Write, [
                $owner(), $repo(),
                new Param('title', 'string', true, 'Título'),
                new Param('head', 'string', true, 'Rama origen'),
                new Param('base', 'string', true, 'Rama destino'),
                new Param('body', 'string', false, 'Descripción'),
            ]),

            new Action('actions.runs.list', 'Listar runs de Actions', 'Lista ejecuciones de GitHub Actions', ActionAccess::Read, [
                $owner(), $repo(),
                new Param('per_page', 'integer', false, 'Cantidad de resultados', default: 10),
            ]),
            new Action('actions.runs.rerun', 'Re-ejecutar run', 'Vuelve a ejecutar un workflow run', ActionAccess::Write, [
                $owner(), $repo(),
                new Param('run_id', 'integer', true, 'ID del run'),
            ]),

            new Action('notifications.list', 'Listar notificaciones', 'Notificaciones del usuario autenticado', ActionAccess::Read, [
                new Param('all', 'boolean', false, 'Incluir leídas'),
                new Param('per_page', 'integer', false, 'Cantidad de resultados', default: 20),
            ]),

            new Action('search.code', 'Buscar código', 'Busca código en GitHub', ActionAccess::Read, [
                new Param('q', 'string', true, 'Consulta de búsqueda'),
            ]),
            new Action('search.issues', 'Buscar issues y PRs', 'Busca issues y PRs en GitHub', ActionAccess::Read, [
                new Param('q', 'string', true, 'Consulta de búsqueda'),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return match ($key) {
            'repos.list' => $this->result(
                $this->request($connection, new HttpCall('GET', 'user/repos', query: [
                    'sort' => 'updated',
                    'per_page' => $params['per_page'] ?? 30,
                ])),
                'Repositorios listados.',
            ),
            'repos.get' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}")),
                'Repositorio obtenido.',
            ),
            'issues.list' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}/issues", query: [
                    'state' => $params['state'] ?? 'open',
                    'per_page' => $params['per_page'] ?? 30,
                ])),
                'Issues listados.',
            ),
            'issues.get' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}/issues/{$params['number']}")),
                'Issue obtenido.',
            ),
            'issues.create' => $this->result(
                $this->request($connection, new HttpCall('POST', "repos/{$params['owner']}/{$params['repo']}/issues", json: [
                    'title' => $params['title'],
                    'body' => $params['body'] ?? null,
                ])),
                'Issue creado.',
            ),
            'issues.comment' => $this->result(
                $this->request($connection, new HttpCall('POST', "repos/{$params['owner']}/{$params['repo']}/issues/{$params['number']}/comments", json: [
                    'body' => $params['body'],
                ])),
                'Comentario agregado.',
            ),
            'issues.close' => $this->result(
                $this->request($connection, new HttpCall('PATCH', "repos/{$params['owner']}/{$params['repo']}/issues/{$params['number']}", json: [
                    'state' => 'closed',
                ])),
                'Issue cerrado.',
            ),
            'pulls.list' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}/pulls", query: [
                    'state' => $params['state'] ?? 'open',
                ])),
                'Pull requests listados.',
            ),
            'pulls.get' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}/pulls/{$params['number']}")),
                'Pull request obtenido.',
            ),
            'pulls.files' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}/pulls/{$params['number']}/files")),
                'Archivos del PR listados.',
            ),
            'pulls.create' => $this->result(
                $this->request($connection, new HttpCall('POST', "repos/{$params['owner']}/{$params['repo']}/pulls", json: [
                    'title' => $params['title'],
                    'head' => $params['head'],
                    'base' => $params['base'],
                    'body' => $params['body'] ?? null,
                ])),
                'Pull request creado.',
            ),
            'actions.runs.list' => $this->result(
                $this->request($connection, new HttpCall('GET', "repos/{$params['owner']}/{$params['repo']}/actions/runs", query: [
                    'per_page' => $params['per_page'] ?? 10,
                ])),
                'Runs listados.',
            ),
            'actions.runs.rerun' => $this->result(
                $this->request($connection, new HttpCall('POST', "repos/{$params['owner']}/{$params['repo']}/actions/runs/{$params['run_id']}/rerun")),
                'Run re-ejecutado.',
            ),
            'notifications.list' => $this->result(
                $this->request($connection, new HttpCall('GET', 'notifications', query: array_filter([
                    'all' => $params['all'] ?? null,
                    'per_page' => $params['per_page'] ?? 20,
                ], fn (mixed $value): bool => $value !== null))),
                'Notificaciones listadas.',
            ),
            'search.code' => $this->result(
                $this->request($connection, new HttpCall('GET', 'search/code', query: ['q' => $params['q']])),
                'Búsqueda de código completada.',
            ),
            'search.issues' => $this->result(
                $this->request($connection, new HttpCall('GET', 'search/issues', query: ['q' => $params['q']])),
                'Búsqueda de issues completada.',
            ),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $response = $this->request($connection, new HttpCall('GET', 'user'));

        return $response->ok
            ? ConnectionTestResult::ok('GitHub OK', ['login' => $response->data['login'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'GitHub no respondió correctamente.');
    }
}
