<?php

namespace App\Storage;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\Param;
use App\Integrations\Connectors\AbstractConnector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use Illuminate\Support\Str;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\TemporaryUrlGenerator;
use Throwable;

abstract class AbstractStorageConnector extends AbstractConnector
{
    public const READ_CAP_BYTES = 262144;

    public function group(): string
    {
        return 'Archivos';
    }

    public function authFields(): array
    {
        return [];
    }

    /**
     * @return array<int, array{name: string, label: string, placeholder: string, required: bool}>
     */
    public function optionFields(): array
    {
        return [];
    }

    public function actions(): array
    {
        $path = fn (bool $required = true): Param => new Param('path', 'string', $required, 'Ruta dentro del disco', default: $required ? null : '/');

        return [
            new Action('files.list', 'Listar', 'Lista archivos y carpetas', ActionAccess::Read, [
                $path(false),
                new Param('limit', 'integer', false, 'Máximo de entradas', default: 200),
            ]),
            new Action('files.stat', 'Ver metadata', 'Metadata de un archivo o carpeta', ActionAccess::Read, [$path()]),
            new Action('files.read', 'Leer archivo', 'Lee un archivo de texto (máx 256 KB)', ActionAccess::Read, [$path()]),
            new Action('files.upload', 'Subir texto', 'Crea/sobrescribe un archivo con contenido de texto', ActionAccess::Write, [
                $path(),
                new Param('content', 'string', true, 'Contenido'),
            ]),
            new Action('files.mkdir', 'Crear carpeta', 'Crea una carpeta', ActionAccess::Write, [$path()]),
            new Action('files.move', 'Mover', 'Mueve o renombra', ActionAccess::Write, [
                new Param('from', 'string', true, 'Origen'),
                new Param('to', 'string', true, 'Destino'),
            ]),
            new Action('files.copy', 'Copiar', 'Copia un archivo', ActionAccess::Write, [
                new Param('from', 'string', true, 'Origen'),
                new Param('to', 'string', true, 'Destino'),
            ]),
            new Action('files.share', 'Compartir', 'Genera un link temporal (si el proveedor lo soporta)', ActionAccess::Write, [
                $path(),
                new Param('ttl_minutes', 'integer', false, 'Minutos de validez', default: 60),
            ]),
            new Action('files.delete', 'Eliminar', 'Elimina un archivo o carpeta', ActionAccess::Destructive, [
                $path(),
                new Param('recursive', 'boolean', false, 'Borrar carpeta con contenido', default: false),
            ]),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        $path = $this->safePath((string) ($params['path'] ?? '/'));

        if ($path === null) {
            return ActionResult::failure('Ruta inválida (no se permite salir del disco).');
        }

        try {
            $filesystem = $this->filesystem($connection);

            return match ($key) {
                'files.list' => $this->list($filesystem, $path, (int) ($params['limit'] ?? 200)),
                'files.stat' => $this->stat($filesystem, $path),
                'files.read' => $this->read($filesystem, $path),
                'files.upload' => $this->upload($filesystem, $path, (string) $params['content']),
                'files.mkdir' => $this->mkdir($filesystem, $path),
                'files.move' => $this->move($filesystem, $params),
                'files.copy' => $this->copy($filesystem, $params),
                'files.share' => $this->share($connection, $path, (int) ($params['ttl_minutes'] ?? 60)),
                'files.delete' => $this->delete($filesystem, $path, (bool) ($params['recursive'] ?? false)),
                default => ActionResult::failure("Acción desconocida [{$key}]."),
            };
        } catch (Throwable $e) {
            return ActionResult::failure(Str::limit($e->getMessage(), 300));
        }
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        try {
            $filesystem = $this->filesystem($connection);
            $filesystem->listContents('/')->toArray();

            return ConnectionTestResult::ok('Storage OK', ['root' => $this->root($connection)]);
        } catch (Throwable $e) {
            return ConnectionTestResult::fail(Str::limit($e->getMessage(), 300));
        }
    }

    protected function filesystem(Connection $connection): FilesystemOperator
    {
        return app(StorageManager::class)->for($connection);
    }

    protected function root(Connection $connection): string
    {
        return (string) ($connection->options['root'] ?? '/');
    }

    protected function safePath(string $path): ?string
    {
        if (str_contains($path, '..')) {
            return null;
        }

        return trim($path, '/');
    }

    protected function list(FilesystemOperator $filesystem, string $path, int $limit): ActionResult
    {
        $entries = collect($filesystem->listContents($path === '' ? '/' : $path, false))
            ->take(max(1, $limit))
            ->map(fn (StorageAttributes $entry): array => $this->entry($entry))
            ->values()
            ->all();

        return ActionResult::success('Listado obtenido.', $entries);
    }

    protected function stat(FilesystemOperator $filesystem, string $path): ActionResult
    {
        if ($filesystem->directoryExists($path)) {
            return ActionResult::success('Metadata obtenida.', [
                'path' => $path,
                'name' => basename($path),
                'type' => 'dir',
            ]);
        }

        if (! $filesystem->fileExists($path)) {
            return ActionResult::failure('El archivo o carpeta no existe.');
        }

        return ActionResult::success('Metadata obtenida.', [
            'path' => $path,
            'name' => basename($path),
            'type' => 'file',
            'size' => $filesystem->fileSize($path),
            'mime' => $filesystem->mimeType($path),
            'last_modified' => $filesystem->lastModified($path),
        ]);
    }

    protected function read(FilesystemOperator $filesystem, string $path): ActionResult
    {
        $stream = $filesystem->readStream($path);

        if ($stream === null) {
            return ActionResult::failure('No se pudo abrir el archivo.');
        }

        $content = stream_get_contents($stream, self::READ_CAP_BYTES) ?: '';
        fclose($stream);

        return ActionResult::success('Archivo leído.', [
            'path' => $path,
            'content' => $content,
            'truncated' => strlen($content) >= self::READ_CAP_BYTES,
        ]);
    }

    protected function upload(FilesystemOperator $filesystem, string $path, string $content): ActionResult
    {
        $filesystem->write($path, $content);

        return ActionResult::success('Archivo subido.', ['path' => $path, 'bytes' => strlen($content)]);
    }

    protected function mkdir(FilesystemOperator $filesystem, string $path): ActionResult
    {
        $filesystem->createDirectory($path);

        return ActionResult::success('Carpeta creada.', ['path' => $path]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function move(FilesystemOperator $filesystem, array $params): ActionResult
    {
        $from = $this->safePath((string) $params['from']);
        $to = $this->safePath((string) $params['to']);

        if ($from === null || $to === null) {
            return ActionResult::failure('Ruta inválida.');
        }

        $filesystem->move($from, $to);

        return ActionResult::success('Movido.', ['from' => $from, 'to' => $to]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function copy(FilesystemOperator $filesystem, array $params): ActionResult
    {
        $from = $this->safePath((string) $params['from']);
        $to = $this->safePath((string) $params['to']);

        if ($from === null || $to === null) {
            return ActionResult::failure('Ruta inválida.');
        }

        $filesystem->copy($from, $to);

        return ActionResult::success('Copiado.', ['from' => $from, 'to' => $to]);
    }

    protected function share(Connection $connection, string $path, int $ttlMinutes): ActionResult
    {
        $adapter = app(StorageManager::class)->adapterFor($connection);

        if (! $adapter instanceof TemporaryUrlGenerator) {
            return ActionResult::failure('Este proveedor no soporta links temporales.');
        }

        $url = $adapter->temporaryUrl(
            $path,
            now()->addMinutes(max(5, min(10080, $ttlMinutes)))->toDateTimeImmutable(),
        );

        return ActionResult::success('Link temporal generado.', ['url' => $url]);
    }

    protected function delete(FilesystemOperator $filesystem, string $path, bool $recursive): ActionResult
    {
        if ($path === '') {
            return ActionResult::failure('No se puede borrar la raíz del disco.');
        }

        if ($recursive) {
            $filesystem->deleteDirectory($path);
        } else {
            $filesystem->delete($path);
        }

        return ActionResult::success('Eliminado.', ['path' => $path]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function entry(StorageAttributes $entry): array
    {
        $data = [
            'path' => $entry->path(),
            'name' => basename($entry->path()),
            'type' => $entry instanceof DirectoryAttributes ? 'dir' : 'file',
        ];

        if ($entry instanceof FileAttributes) {
            $data['size'] = $entry->fileSize();
            $data['mime'] = $entry->mimeType();
            $data['last_modified'] = $entry->lastModified();
        }

        return $data;
    }
}
