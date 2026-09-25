<?php

namespace App\Integrations\Connectors\Listenbrainz;

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

class ListenbrainzConnector extends AbstractConnector
{
    public function kind(): string
    {
        return 'listenbrainz';
    }

    public function label(): string
    {
        return 'ListenBrainz';
    }

    public function group(): string
    {
        return 'Música';
    }

    public function description(): string
    {
        return 'Scrobbles y estadísticas de escucha.';
    }

    public function defaultBaseUrl(): ?string
    {
        return 'https://api.listenbrainz.org';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                name: 'token',
                type: 'password',
                label: 'User token',
                required: false,
                help: 'Opcional: sin token funcionan las lecturas públicas. Fijá el usuario en opciones o pasalo por parámetro.',
            ),
        ];
    }

    public function actions(): array
    {
        $user = fn (): Param => new Param('user', 'string', false, 'Usuario (default: el de la conexión)');
        $range = fn (): Param => new Param('range', 'string', false, 'Rango', enum: ['week', 'month', 'year', 'all_time'], default: 'week');

        return [
            new Action('user.get', 'Ver usuario', 'Perfil de ListenBrainz', ActionAccess::Read, [$user()]),
            new Action('listens.recent', 'Escuchas recientes', 'Últimas escuchas', ActionAccess::Read, [
                $user(),
                new Param('count', 'integer', false, 'Cantidad', default: 20),
            ]),
            new Action('stats.top_artists', 'Top artistas', 'Artistas más escuchados', ActionAccess::Read, [$user(), $range()]),
            new Action('stats.top_recordings', 'Top canciones', 'Canciones más escuchadas', ActionAccess::Read, [$user(), $range()]),
            new Action('stats.listening_activity', 'Actividad', 'Actividad de escucha', ActionAccess::Read, [$user(), $range()]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $user = $this->user($connection, $params);

        if ($user === '') {
            return ActionResult::failure('Falta el usuario: configuralo en opciones o pasalo por parámetro.');
        }

        $range = $params['range'] ?? 'week';

        return match ($key) {
            'user.get' => $this->result($this->api($connection, "1/user/{$user}"), 'Usuario obtenido.'),
            'listens.recent' => $this->result(
                $this->api($connection, "1/user/{$user}/listens", ['count' => $params['count'] ?? 20]),
                'Escuchas obtenidas.',
            ),
            'stats.top_artists' => $this->result(
                $this->api($connection, "1/stats/user/{$user}/artists", ['range' => $range]),
                'Top artistas obtenido.',
            ),
            'stats.top_recordings' => $this->result(
                $this->api($connection, "1/stats/user/{$user}/recordings", ['range' => $range]),
                'Top canciones obtenido.',
            ),
            'stats.listening_activity' => $this->result(
                $this->api($connection, "1/stats/user/{$user}/listening-activity", ['range' => $range]),
                'Actividad obtenida.',
            ),
            default => ActionResult::failure("Acción desconocida [{$key}]."),
        };
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        $user = $this->user($connection, []);

        if ($user === '') {
            return ConnectionTestResult::fail('Falta el usuario en opciones para probar la conexión.');
        }

        $response = $this->api($connection, "1/user/{$user}");

        return $response->ok
            ? ConnectionTestResult::ok('ListenBrainz OK', ['username' => $response->data['username'] ?? null])
            : ConnectionTestResult::fail($response->error ?? 'ListenBrainz no respondió.');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function user(Connection $connection, array $params): string
    {
        return (string) ($params['user'] ?? $connection->options['username'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function api(Connection $connection, string $path, array $query = []): HttpResult
    {
        $headers = [];
        $token = (string) ($connection->credentials['token'] ?? '');

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->request($connection, new HttpCall(
            method: 'GET',
            path: $path,
            query: $query,
            headers: $headers,
        ));
    }
}
