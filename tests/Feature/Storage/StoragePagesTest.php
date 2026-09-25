<?php

use App\Models\Connection;
use App\Models\User;
use App\Storage\StorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

beforeEach(function () {
    $this->withoutVite();
    $this->root = sys_get_temp_dir().'/mega-storage-pages-'.uniqid();
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

function storageDisk(User $user, array $attributes = []): Connection
{
    return Connection::factory()->for($user)->create(array_merge([
        'kind' => 'storage_local',
        'auth_type' => 'none',
        'credentials' => [],
        'options' => ['root' => '/tmp'],
    ], $attributes));
}

it('renders the storage browser with disks and entries', function () {
    $user = User::factory()->create();
    $disk = storageDisk($user);

    $this->actingAs($user)->get(route('storage.index', ['disk' => $disk->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('storage/index')
            ->has('disks', 1)
            ->where('selectedDisk', $disk->id)
            ->has('entries'));
});

it('uploads, lists, makes folders and deletes through the executor', function () {
    $user = User::factory()->create();
    $disk = storageDisk($user);

    $this->actingAs($user)->post(route('storage.upload'), [
        'disk_id' => $disk->id,
        'path' => '/',
        'file' => UploadedFile::fake()->createWithContent('nota.txt', 'contenido'),
    ])->assertRedirect();

    expect(File::exists($this->root.'/nota.txt'))->toBeTrue();

    $this->actingAs($user)->get(route('storage.browse', ['disk_id' => $disk->id, 'path' => '/']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'nota.txt']);

    $this->actingAs($user)->post(route('storage.mkdir'), ['disk_id' => $disk->id, 'path' => 'docs'])->assertRedirect();
    expect(File::isDirectory($this->root.'/docs'))->toBeTrue();

    $this->actingAs($user)->delete(route('storage.destroy'), [
        'disk_id' => $disk->id,
        'path' => 'nota.txt',
    ])->assertRedirect();

    expect(File::exists($this->root.'/nota.txt'))->toBeFalse();
});

it('downloads a file', function () {
    $user = User::factory()->create();
    $disk = storageDisk($user);
    File::put($this->root.'/hola.txt', 'hola mundo');

    $this->actingAs($user)
        ->get(route('storage.download', ['disk_id' => $disk->id, 'path' => 'hola.txt']))
        ->assertOk()
        ->assertDownload('hola.txt');
});

it('reports unsupported shares for local disks', function () {
    $user = User::factory()->create();
    $disk = storageDisk($user);
    File::put($this->root.'/hola.txt', 'x');

    $this->actingAs($user)->postJson(route('storage.share'), [
        'disk_id' => $disk->id,
        'path' => 'hola.txt',
    ])->assertOk()->assertJson(['ok' => false]);
});

it('404s foreign disks', function () {
    $disk = storageDisk(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('storage.index', ['disk' => $disk->id]))
        ->assertNotFound();
});
