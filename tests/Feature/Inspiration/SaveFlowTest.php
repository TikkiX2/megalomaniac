<?php

declare(strict_types=1);

use App\Inspiration\Exceptions\DownloadQuotaExceededException;
use App\Inspiration\Exceptions\DuplicateSavedImageException;
use App\Inspiration\InspirationSaveService;
use App\Jobs\Inspiration\DownloadFullJob;
use App\Jobs\Inspiration\DownloadThumbJob;
use App\Models\Moodboard;
use App\Models\Project;
use App\Models\SavedImage;
use App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InspirationSaveService::class);
});

/**
 * Create a board directly (Inbox or project-scoped) without the service.
 */
function saveFlowBoard(User $user, ?Project $project = null): Moodboard
{
    return Moodboard::create([
        'user_id' => $user->id,
        'project_id' => $project?->id,
        'name' => $project?->name ?? 'Inbox',
    ]);
}

/**
 * Persist a saved image row directly, with a null thumb_path by default.
 *
 * @param  array<string, mixed>  $overrides
 */
function saveFlowImage(User $user, Moodboard $board, array $overrides = []): SavedImage
{
    return SavedImage::create(array_merge([
        'user_id' => $user->id,
        'moodboard_id' => $board->id,
        'source' => 'deviantart',
        'source_id' => 'abc-123',
        'page_url' => 'https://example.com/page',
        'image_url' => 'https://93.184.216.34/full.jpg',
        'download_status' => SavedImage::STATUS_THUMB,
    ], $overrides));
}

/**
 * Validated save payload shape (snake_case, as the Form Request emits it).
 *
 * The image/thumbnail hosts are public IP literals so the SSRF rule does not
 * depend on DNS resolution during tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function saveFlowPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'deviantart',
        'source_id' => 'abc-123',
        'page_url' => 'https://93.184.216.34/page',
        'image_url' => 'https://93.184.216.34/full.jpg',
        'thumbnail_url' => 'https://93.184.216.34/thumb.jpg',
    ], $overrides);
}

it('returns the same inbox board on repeated calls', function () {
    $user = User::factory()->create();

    $first = $this->service->ensureInbox($user);
    $second = $this->service->ensureInbox($user);

    expect($second->is($first))->toBeTrue()
        ->and($first->project_id)->toBeNull()
        ->and($first->name)->toBe('Inbox')
        ->and(Moodboard::forUser($user)->whereNull('project_id')->count())->toBe(1);
});

it('creates a board lazily for a personal project and reuses it', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['type' => 'personal', 'name' => 'Portfolio']);

    $board = $this->service->ensureMoodboardForProject($user, $project);
    $again = $this->service->ensureMoodboardForProject($user, $project);

    expect($board->project_id)->toBe($project->id)
        ->and($board->name)->toBe('Portfolio')
        ->and($again->is($board))->toBeTrue();
});

it('refuses to create a board for a project owned by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['type' => 'personal']);

    expect(fn () => $this->service->ensureMoodboardForProject($intruder, $project))
        ->toThrow(AuthorizationException::class);
});

it('refuses to create a board for a non-personal project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['type' => 'freelance']);

    expect(fn () => $this->service->ensureMoodboardForProject($user, $project))
        ->toThrow(AuthorizationException::class);
});

it('lists the inbox and project boards with their counts', function () {
    $user = User::factory()->create();
    $inbox = $this->service->ensureInbox($user);
    $project = Project::factory()->for($user)->create(['type' => 'personal', 'name' => 'Portfolio']);
    $projectBoard = $this->service->ensureMoodboardForProject($user, $project);

    saveFlowImage($user, $inbox, ['source_id' => 'one']);
    saveFlowImage($user, $inbox, ['source_id' => 'two']);

    $boards = $this->service->boards($user);

    expect($boards)->toHaveCount(2)
        ->and($boards->first()->is($inbox))->toBeTrue()
        ->and($boards->first()->project_name)->toBeNull()
        ->and($boards->first()->count)->toBe(2)
        ->and($boards->last()->is($projectBoard))->toBeTrue()
        ->and($boards->last()->project_name)->toBe('Portfolio')
        ->and($boards->last()->count)->toBe(0);
});

it('saves an image and queues its thumbnail download', function () {
    Queue::fake();
    $user = User::factory()->create();
    $board = $this->service->ensureInbox($user);

    $image = $this->service->save($user, saveFlowPayload(), $board);

    expect($image->exists)->toBeTrue()
        ->and($image->user_id)->toBe($user->id)
        ->and($image->moodboard_id)->toBe($board->id)
        ->and($image->download_status)->toBe(SavedImage::STATUS_THUMB)
        ->and($image->thumb_path)->toBeNull();

    Queue::assertPushed(DownloadThumbJob::class, fn (DownloadThumbJob $job): bool => $job->saved->is($image));
});

it('stores a thumbnail locally when the remote image responds', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response('binary-image', 200, ['Content-Type' => 'image/jpeg'])]);
    $user = User::factory()->create();
    $board = $this->service->ensureInbox($user);
    $image = $this->service->save($user, saveFlowPayload(), $board);

    DownloadThumbJob::dispatchSync($image, 'https://93.184.216.34/thumb.jpg');

    $expected = 'inspiration/'.$user->id.'/deviantart/abc-123.thumb.jpg';

    expect($image->refresh()->thumb_path)->toBe($expected)
        ->and($image->download_status)->toBe(SavedImage::STATUS_THUMB);

    Storage::disk('local')->assertExists($expected);
});

it('keeps the remote url fallback when the thumbnail download fails', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response('', 404)]);
    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board);

    DownloadThumbJob::dispatchSync($image, 'https://93.184.216.34/broken.jpg');

    expect($image->refresh()->thumb_path)->toBeNull()
        ->and($image->download_status)->toBe(SavedImage::STATUS_THUMB);
});

it('raises a duplicate exception carrying the existing saved image', function () {
    Queue::fake();
    $user = User::factory()->create();
    $board = $this->service->ensureInbox($user);

    $first = $this->service->save($user, saveFlowPayload(), $board);

    try {
        $this->service->save($user, saveFlowPayload(), $board);
        $this->fail('Expected DuplicateSavedImageException.');
    } catch (DuplicateSavedImageException $exception) {
        expect($exception->existing->is($first))->toBeTrue()
            ->and($exception->existing->source_id)->toBe('abc-123');
    }

    expect(SavedImage::forUser($user)->count())->toBe(1);
});

it('deletes a saved image through the endpoint and lowers the board count', function () {
    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board);

    $this->actingAs($user)
        ->delete("/inspiration/saved/{$image->id}")
        ->assertNoContent();

    expect(SavedImage::find($image->id))->toBeNull()
        ->and($board->savedImages()->count())->toBe(0);
});

it('hides another user saved image behind a 404', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $board = saveFlowBoard($owner);
    $image = saveFlowImage($owner, $board);

    $this->actingAs($intruder)
        ->delete("/inspiration/saved/{$image->id}")
        ->assertNotFound();

    $this->actingAs($intruder)
        ->postJson("/inspiration/saved/{$image->id}/download")
        ->assertNotFound();

    expect(SavedImage::find($image->id))->not->toBeNull();
});

it('exhausts the daily download quota and refuses a full download', function () {
    $user = User::factory()->create();
    $board = saveFlowBoard($user);

    for ($i = 0; $i < 20; $i++) {
        saveFlowImage($user, $board, [
            'source_id' => "quota-{$i}",
            'downloaded_at' => now(),
        ]);
    }

    $target = saveFlowImage($user, $board, ['source_id' => 'target']);

    expect(fn () => $this->service->requestFullDownload($user, $target))
        ->toThrow(DownloadQuotaExceededException::class);
});

it('queues and stores a full download under the daily quota', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response('full-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board, ['source_id' => 'target']);

    Queue::fake();
    $this->service->requestFullDownload($user, $image);
    Queue::assertPushed(DownloadFullJob::class, fn (DownloadFullJob $job): bool => $job->saved->is($image));

    // Queue::fake() replaces the "sync" queue connection, so both dispatch() and
    // dispatchSync() only record the job and never reach handle(). Run handle()
    // manually here to exercise the real download path.
    (new DownloadFullJob($image))->handle();

    $expected = 'inspiration/'.$user->id.'/deviantart/target.full.jpg';

    expect($image->refresh()->download_status)->toBe(SavedImage::STATUS_FULL)
        ->and($image->downloaded_at)->not->toBeNull()
        ->and($image->full_path)->toBe($expected);

    Storage::disk('local')->assertExists($expected);
});

it('marks the saved image as failed when the full download times out', function () {
    Storage::fake('local');
    Http::fake(['*' => fn () => throw new ConnectionException('Connection timed out')]);
    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board);

    DownloadFullJob::dispatchSync($image);

    expect($image->refresh()->download_status)->toBe(SavedImage::STATUS_FAILED)
        ->and($image->downloaded_at)->toBeNull();
});

it('saves through the endpoint with 201 and reports duplicates with 409', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inspiration/save', saveFlowPayload())
        ->assertCreated()
        ->assertJsonPath('saved_image.download_status', SavedImage::STATUS_THUMB);

    $this->actingAs($user)
        ->postJson('/inspiration/save', saveFlowPayload())
        ->assertStatus(409)
        ->assertJsonPath('existing_moodboard_name', 'Inbox')
        ->assertJsonStructure(['message', 'existing_moodboard_id', 'existing_moodboard_name']);
});

it('rejects a save for an unknown source', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inspiration/save', saveFlowPayload(['source' => 'nope']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

it('answers 429 when the download quota is exhausted through the endpoint', function () {
    $user = User::factory()->create();
    $board = saveFlowBoard($user);

    for ($i = 0; $i < 20; $i++) {
        saveFlowImage($user, $board, [
            'source_id' => "quota-{$i}",
            'downloaded_at' => now(),
        ]);
    }

    $target = saveFlowImage($user, $board, ['source_id' => 'target']);

    $this->actingAs($user)
        ->postJson("/inspiration/saved/{$target->id}/download")
        ->assertStatus(429)
        ->assertJsonPath('message', 'límite diario alcanzado');
});

it('answers 202 and queues a full download through the endpoint', function () {
    Queue::fake();
    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board);

    $this->actingAs($user)
        ->postJson("/inspiration/saved/{$image->id}/download")
        ->assertStatus(202)
        ->assertJson(['status' => 'queued']);

    Queue::assertPushed(DownloadFullJob::class);
});

it('returns the current status without queuing when already downloaded', function () {
    Queue::fake();
    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board, [
        'download_status' => SavedImage::STATUS_FULL,
        'downloaded_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson("/inspiration/saved/{$image->id}/download")
        ->assertOk()
        ->assertJson(['status' => SavedImage::STATUS_FULL]);

    Queue::assertNothingPushed();
});

it('rejects saving into another user board with a 404', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $foreignBoard = saveFlowBoard($owner);

    $this->actingAs($intruder)
        ->postJson('/inspiration/save', saveFlowPayload(['moodboard_id' => $foreignBoard->id]))
        ->assertNotFound();

    expect(SavedImage::count())->toBe(0);
});

it('refuses a full download for another user saved image', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $board = saveFlowBoard($owner);
    $image = saveFlowImage($owner, $board);

    expect(fn () => $this->service->requestFullDownload($intruder, $image))
        ->toThrow(AuthorizationException::class);
});

it('rejects a save whose image url points at a private host', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inspiration/save', saveFlowPayload(['image_url' => 'http://127.0.0.1/secret.jpg']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('image_url');

    expect(SavedImage::count())->toBe(0);
});

it('rejects a save whose thumbnail url points at a private host', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inspiration/save', saveFlowPayload(['thumbnail_url' => 'http://192.168.0.10/thumb.jpg']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('thumbnail_url');

    expect(SavedImage::count())->toBe(0);
});

it('rejects a save with a non-http image url scheme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/inspiration/save', saveFlowPayload(['image_url' => 'data:image/png;base64,AAAA']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('image_url');

    expect(SavedImage::count())->toBe(0);
});

it('aborts a thumbnail download redirected to a private host', function () {
    Storage::fake('local');

    $mock = new MockHandler([
        new GuzzleResponse(302, ['Location' => 'http://127.0.0.1/evil.jpg']),
        new GuzzleResponse(200, [], 'evil-bytes'),
    ]);
    Http::globalOptions(['handler' => HandlerStack::create($mock)]);

    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board);

    DownloadThumbJob::dispatchSync($image, 'https://93.184.216.34/thumb.jpg');

    expect($image->refresh()->thumb_path)->toBeNull()
        ->and($image->download_status)->toBe(SavedImage::STATUS_THUMB);

    Storage::disk('local')->assertMissing('inspiration/'.$user->id.'/deviantart/abc-123.thumb.jpg');
});

it('marks a full download as failed when redirected to a private host', function () {
    Storage::fake('local');

    $mock = new MockHandler([
        new GuzzleResponse(302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        new GuzzleResponse(200, [], 'evil-bytes'),
    ]);
    Http::globalOptions(['handler' => HandlerStack::create($mock)]);

    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board);

    DownloadFullJob::dispatchSync($image);

    expect($image->refresh()->download_status)->toBe(SavedImage::STATUS_FAILED)
        ->and($image->downloaded_at)->toBeNull()
        ->and($image->full_path)->toBeNull();
});

it('stores the image when a redirect stays on a public host', function () {
    Storage::fake('local');

    $mock = new MockHandler([
        new GuzzleResponse(302, ['Location' => 'https://93.184.216.34/final.jpg']),
        new GuzzleResponse(200, [], 'public-bytes'),
    ]);
    Http::globalOptions(['handler' => HandlerStack::create($mock)]);

    $user = User::factory()->create();
    $board = saveFlowBoard($user);
    $image = saveFlowImage($user, $board, ['source_id' => 'redirected']);

    DownloadThumbJob::dispatchSync($image, 'https://93.184.216.34/thumb.jpg');

    $expected = 'inspiration/'.$user->id.'/deviantart/redirected.thumb.jpg';

    expect($image->refresh()->thumb_path)->toBe($expected);
    Storage::disk('local')->assertExists($expected);
});

it('translates a concurrent unique violation into a duplicate exception', function () {
    Queue::fake();
    $user = User::factory()->create();
    $board = $this->service->ensureInbox($user);

    $injected = false;

    SavedImage::creating(function (SavedImage $model) use (&$injected): void {
        if ($injected) {
            return;
        }

        $injected = true;

        // Simulate a concurrent request winning the race between the service
        // pre-check and its insert.
        DB::table('saved_images')->insert([
            'user_id' => $model->user_id,
            'moodboard_id' => $model->moodboard_id,
            'source' => $model->source,
            'source_id' => $model->source_id,
            'page_url' => $model->page_url,
            'image_url' => $model->image_url,
            'download_status' => SavedImage::STATUS_THUMB,
        ]);
    });

    try {
        $this->service->save($user, saveFlowPayload(), $board);
        $this->fail('Expected DuplicateSavedImageException.');
    } catch (DuplicateSavedImageException $exception) {
        expect($exception->existing->source_id)->toBe('abc-123');
    } finally {
        SavedImage::flushEventListeners();
    }

    expect(SavedImage::count())->toBe(1);
});
