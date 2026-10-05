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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
        'image_url' => 'https://cdn.example.com/full.jpg',
        'download_status' => SavedImage::STATUS_THUMB,
    ], $overrides));
}

/**
 * Validated save payload shape (snake_case, as the Form Request emits it).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function saveFlowPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'deviantart',
        'source_id' => 'abc-123',
        'page_url' => 'https://example.com/page',
        'image_url' => 'https://cdn.example.com/full.jpg',
        'thumbnail_url' => 'https://cdn.example.com/thumb.jpg',
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

    DownloadThumbJob::dispatchSync($image, 'https://cdn.example.com/thumb.jpg');

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

    DownloadThumbJob::dispatchSync($image, 'https://cdn.example.com/broken.jpg');

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

    // Queue::fake() intercepts dispatchSync for queueable jobs, so run the job directly.
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
