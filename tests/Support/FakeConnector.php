<?php

namespace Tests\Support;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\IntegrationExecutor;
use App\Models\Connection;

class FakeConnector implements Connector
{
    public static int $calls = 0;

    public static bool $shouldFail = false;

    public function kind(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function group(): string
    {
        return 'Test';
    }

    public function description(): string
    {
        return 'Fake connector';
    }

    public function authFields(): array
    {
        return [];
    }

    public function transports(): array
    {
        return ['direct'];
    }

    public function defaultBaseUrl(): ?string
    {
        return null;
    }

    public function actions(): array
    {
        return [
            new Action('ping', 'Ping', 'Ping', ActionAccess::Read, [
                new Param('secret_token', 'string', false, 'Token', sensitive: true),
            ]),
            new Action('write', 'Write', 'Write', ActionAccess::Write),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        self::$calls++;

        return self::$shouldFail
            ? ActionResult::failure('falló')
            : ActionResult::success('hecho', ['key' => $key]);
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('ok');
    }

    public static function reset(): void
    {
        self::$calls = 0;
        self::$shouldFail = false;
    }

    public static function executor(): IntegrationExecutor
    {
        return new IntegrationExecutor(new ConnectorRegistry([self::class]));
    }
}
