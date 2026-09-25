<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\IntegrationExecutor;
use App\Models\Connection;
use App\Storage\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use League\Flysystem\StorageAttributes;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    public function __construct(
        private readonly StorageManager $manager,
        private readonly IntegrationExecutor $executor,
    ) {}

    public function index(Request $request): Response
    {
        $disks = $this->disks($request);
        $selectedId = (int) ($request->query('disk') ?? $disks->first()['id'] ?? 0);
        $path = (string) $request->query('path', '/');

        $entries = [];

        if ($selectedId > 0) {
            $disk = $this->ownedDisk($request, $selectedId);
            $entries = $this->entries($disk, $path);
        }

        return Inertia::render('storage/index', [
            'disks' => $disks,
            'selectedDisk' => $selectedId,
            'path' => $path,
            'entries' => $entries,
        ]);
    }

    public function browse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'path' => ['nullable', 'string', 'max:1000'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);

        return response()->json([
            'path' => $validated['path'] ?? '/',
            'entries' => $this->entries($disk, $validated['path'] ?? '/'),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'path' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:25600'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);
        $path = trim((string) ($validated['path'] ?? '/'), '/');
        $target = ($path === '' ? '' : $path.'/').$request->file('file')->getClientOriginalName();

        $result = $this->executor->execute($disk, 'files.upload', [
            'path' => $target,
            'content' => (string) file_get_contents($request->file('file')->getRealPath()),
        ], ExecutionContext::forUi($request->user()));

        return back()->with($result->ok ? 'success' : 'error', $result->ok ? 'Archivo subido.' : ($result->error ?? 'Error al subir.'));
    }

    public function mkdir(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);
        $result = $this->executor->execute($disk, 'files.mkdir', ['path' => $validated['path']], ExecutionContext::forUi($request->user()));

        return back()->with($result->ok ? 'success' : 'error', $result->ok ? 'Carpeta creada.' : ($result->error ?? 'Error.'));
    }

    public function move(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'from' => ['required', 'string', 'max:1000'],
            'to' => ['required', 'string', 'max:1000'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);
        $result = $this->executor->execute($disk, 'files.move', [
            'from' => $validated['from'],
            'to' => $validated['to'],
        ], ExecutionContext::forUi($request->user()));

        return back()->with($result->ok ? 'success' : 'error', $result->ok ? 'Movido.' : ($result->error ?? 'Error.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'path' => ['required', 'string', 'max:1000'],
            'recursive' => ['nullable', 'boolean'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);
        $result = $this->executor->execute($disk, 'files.delete', [
            'path' => $validated['path'],
            'recursive' => $validated['recursive'] ?? false,
        ], ExecutionContext::forUi($request->user()));

        return back()->with($result->ok ? 'success' : 'error', $result->ok ? 'Eliminado.' : ($result->error ?? 'Error.'));
    }

    public function share(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);
        $result = $this->executor->execute($disk, 'files.share', ['path' => $validated['path']], ExecutionContext::forUi($request->user()));

        return response()->json([
            'ok' => $result->ok,
            'url' => $result->data['url'] ?? null,
            'error' => $result->error,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'disk_id' => ['required', 'integer'],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        $disk = $this->ownedDisk($request, (int) $validated['disk_id']);
        $filesystem = $this->manager->for($disk);
        $stream = $filesystem->readStream($validated['path']);

        abort_if($stream === null, 404);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($validated['path']));
    }

    /**
     * @return Collection<int, array{id: int, name: string, kind: string}>
     */
    protected function disks(Request $request): Collection
    {
        return Connection::query()
            ->forUser($request->user())
            ->enabled()
            ->where('kind', 'like', 'storage_%')
            ->orderBy('name')
            ->get()
            ->map(fn (Connection $connection): array => [
                'id' => $connection->id,
                'name' => $connection->name,
                'kind' => $connection->kind,
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function entries(Connection $disk, string $path): array
    {
        $normalized = $path === '' ? '/' : $path;

        return collect($this->manager->for($disk)->listContents($normalized, false))
            ->map(function (StorageAttributes $entry): array {
                return [
                    'path' => $entry->path(),
                    'name' => basename($entry->path()),
                    'type' => $entry->isDir() ? 'dir' : 'file',
                    'size' => $entry->isFile() ? $entry->fileSize() : null,
                    'last_modified' => $entry->lastModified(),
                ];
            })
            ->values()
            ->all();
    }

    protected function ownedDisk(Request $request, int $diskId): Connection
    {
        return Connection::query()
            ->forUser($request->user())
            ->enabled()
            ->where('kind', 'like', 'storage_%')
            ->findOrFail($diskId);
    }
}
