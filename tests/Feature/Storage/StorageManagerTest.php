<?php

use App\Models\Connection;
use App\Storage\Connectors\LocalStorageConnector;
use App\Storage\StorageManager;
use Illuminate\Support\Facades\File;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

function localDisk(array $options = [], array $attributes = []): Connection
{
    return Connection::factory()->make(array_merge([
        'kind' => 'storage_local',
        'auth_type' => 'none',
        'credentials' => [],
        'options' => array_merge(['root' => sys_get_temp_dir().'/mega-storage-default'], $options),
    ], $attributes));
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/mega-storage-'.uniqid();
    File::ensureDirectoryExists($this->root);

    app()->instance(StorageManager::class, new class($this->root) extends StorageManager
    {
        public function __construct(private readonly string $root) {}

        public function for(Connection $connection): FilesystemOperator
        {
            return new Filesystem(new LocalFilesystemAdapter($this->root));
        }
    });
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('builds a flysystem adapter for every storage kind', function () {
    $manager = new StorageManager;

    $kinds = [
        'storage_local' => localDisk(['root' => $this->root]),
        'storage_s3' => Connection::factory()->make(['kind' => 'storage_s3', 'credentials' => ['key' => 'k', 'secret' => 's'], 'options' => ['bucket' => 'b', 'region' => 'us-east-1']]),
        'storage_sftp' => Connection::factory()->make(['kind' => 'storage_sftp', 'base_url' => 'sftp://host', 'credentials' => ['username' => 'u', 'password' => 'p'], 'options' => []]),
        'storage_ftp' => Connection::factory()->make(['kind' => 'storage_ftp', 'base_url' => 'ftp://host', 'credentials' => ['username' => 'u', 'password' => 'p'], 'options' => []]),
        'storage_webdav' => Connection::factory()->make(['kind' => 'storage_webdav', 'base_url' => 'https://cloud.test/remote.php/dav', 'credentials' => ['username' => 'u', 'password' => 'p'], 'options' => []]),
        'storage_google_drive' => Connection::factory()->make(['kind' => 'storage_google_drive', 'auth_type' => 'oauth2', 'credentials' => ['access_token' => 'at', 'expires_at' => now()->addHour()->toIso8601String()], 'options' => []]),
        'storage_dropbox' => Connection::factory()->make(['kind' => 'storage_dropbox', 'auth_type' => 'oauth2', 'credentials' => ['access_token' => 'at', 'expires_at' => now()->addHour()->toIso8601String()], 'options' => []]),
    ];

    foreach ($kinds as $kind => $connection) {
        expect($manager->for($connection))->toBeInstanceOf(FilesystemOperator::class, "kind {$kind}");
    }
});

it('lists, uploads, reads, moves, copies and deletes on a disk', function () {
    $connector = new LocalStorageConnector;
    $disk = localDisk(['root' => $this->root]);

    expect($connector->execute($disk, 'files.upload', ['path' => 'docs/nota.txt', 'content' => 'hola'])->ok)->toBeTrue();

    $list = $connector->execute($disk, 'files.list', ['path' => 'docs']);
    expect($list->ok)->toBeTrue()
        ->and($list->data[0]['name'])->toBe('nota.txt')
        ->and($list->data[0]['type'])->toBe('file');

    $read = $connector->execute($disk, 'files.read', ['path' => 'docs/nota.txt']);
    expect($read->data['content'])->toBe('hola');

    expect($connector->execute($disk, 'files.mkdir', ['path' => 'archivo'])->ok)->toBeTrue()
        ->and($connector->execute($disk, 'files.move', ['from' => 'docs/nota.txt', 'to' => 'archivo/nota.txt'])->ok)->toBeTrue()
        ->and($connector->execute($disk, 'files.copy', ['from' => 'archivo/nota.txt', 'to' => 'archivo/copia.txt'])->ok)->toBeTrue();

    $stat = $connector->execute($disk, 'files.stat', ['path' => 'archivo/copia.txt']);
    expect($stat->data['name'])->toBe('copia.txt');

    expect($connector->execute($disk, 'files.delete', ['path' => 'archivo', 'recursive' => true])->ok)->toBeTrue();

    $after = $connector->execute($disk, 'files.list', ['path' => '/']);
    expect(collect($after->data)->pluck('name'))->not->toContain('archivo');
});

it('rejects path traversal and unsupported shares', function () {
    $connector = new LocalStorageConnector;
    $disk = localDisk(['root' => $this->root]);

    $traversal = $connector->execute($disk, 'files.read', ['path' => '../secret.txt']);
    expect($traversal->ok)->toBeFalse()->and($traversal->error)->toContain('Ruta inválida');

    $share = $connector->execute($disk, 'files.share', ['path' => 'x.txt']);
    expect($share->ok)->toBeFalse()->and($share->error)->toContain('no soporta');
});

it('tests a disk connection', function () {
    $connector = new LocalStorageConnector;
    $disk = localDisk(['root' => $this->root]);

    expect($connector->test($disk)->ok)->toBeTrue();
});
