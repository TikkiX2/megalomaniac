<?php

use App\Exceptions\Integrations\UnknownConnectorException;
use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;

class RegistryFakeConnector implements Connector
{
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
        return 'Conector de prueba';
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
        return [new Action('ping', 'Ping', 'Ping the service', ActionAccess::Read)];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return ActionResult::success('pong');
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('ok');
    }
}

it('registers and resolves connectors by kind', function () {
    $registry = new ConnectorRegistry;
    $registry->register(RegistryFakeConnector::class);

    expect($registry->has('fake'))->toBeTrue()
        ->and($registry->for('fake'))->toBeInstanceOf(RegistryFakeConnector::class)
        ->and($registry->all())->toHaveKey('fake');
});

it('throws for unknown connectors', function () {
    $registry = new ConnectorRegistry;

    expect(fn () => $registry->for('nope'))->toThrow(UnknownConnectorException::class);
});

it('resolves the configured registry from the container', function () {
    config(['integrations.connectors' => [RegistryFakeConnector::class]]);

    $registry = app(ConnectorRegistry::class);

    expect($registry->has('fake'))->toBeTrue();
});

it('registers all bundled connectors', function () {
    $registry = app(ConnectorRegistry::class);

    expect(array_keys($registry->all()))->toEqualCanonicalizing([
        'github', 'google', 'docker',
        'sonarr', 'radarr', 'prowlarr', 'jellyseerr', 'qbittorrent', 'jellyfin',
        'proxmox', 'home_assistant',
        'telegram', 'notion', 'rss', 'reddit', 'youtube', 'listenbrainz',
        'storage_local', 'storage_s3', 'storage_sftp', 'storage_ftp',
        'storage_google_drive', 'storage_dropbox', 'storage_webdav',
    ]);
});
