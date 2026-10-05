<?php

declare(strict_types=1);

use App\Models\Moodboard;
use App\Models\Project;
use App\Models\SavedImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * A saved image row for the given board, with sensible defaults.
 *
 * @param  array<string, mixed>  $overrides
 */
function moodboardSavedImage(User $user, Moodboard $board, array $overrides = []): SavedImage
{
    // `created_at` is not fillable, so apply it explicitly after the insert.
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $image = SavedImage::create(array_merge([
        'user_id' => $user->id,
        'moodboard_id' => $board->id,
        'source' => 'wallhaven',
        'source_id' => 'abc-1',
        'title' => 'A portrait',
        'author' => 'someone',
        'page_url' => 'https://example.com/page',
        'image_url' => 'https://example.com/image.jpg',
        'thumb_path' => 'inspiration/'.$user->id.'/wallhaven/abc-1.thumb.jpg',
        'tags' => ['portrait', 'dark'],
    ], $overrides));

    if ($createdAt !== null) {
        $image->forceFill(['created_at' => $createdAt])->save();
    }

    return $image;
}

/**
 * A personal project plus its moodboard for the given user.
 *
 * @return array{0: Moodboard, 1: Project}
 */
function moodboardForProject(User $user, string $name = 'Dark Portraits'): array
{
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'type' => 'personal',
        'name' => $name,
    ]);

    $board = Moodboard::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => $project->name,
    ]);

    return [$board, $project];
}

it('shows an owned moodboard with its items ordered newest first', function () {
    $user = User::factory()->create();
    [$board, $project] = moodboardForProject($user);

    $older = moodboardSavedImage($user, $board, [
        'source_id' => 'older',
        'created_at' => now()->subDay(),
    ]);

    $newer = moodboardSavedImage($user, $board, [
        'source' => 'deviantart',
        'source_id' => 'newer',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get("/inspiration/moodboards/{$board->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inspiration/moodboard', false)
            ->where('board.id', $board->id)
            ->where('board.name', $project->name)
            ->where('board.project_name', $project->name)
            ->has('items', 2)
            ->where('items.0.id', $newer->id)
            ->where('items.1.id', $older->id)
            ->where('items.0.source', 'deviantart')
            ->where('total', 2)
            ->where('sources.deviantart', 1)
            ->where('sources.wallhaven', 1));
});

it('exposes a null project name for the inbox board', function () {
    $user = User::factory()->create();
    $inbox = Moodboard::create([
        'user_id' => $user->id,
        'project_id' => null,
        'name' => 'Inbox',
    ]);

    $this->actingAs($user)
        ->get("/inspiration/moodboards/{$inbox->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('board.id', $inbox->id)
            ->where('board.name', 'Inbox')
            ->where('board.project_name', null)
            ->where('items', [])
            ->where('total', 0));
});

it('returns 404 for a moodboard owned by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    [$board] = moodboardForProject($owner);

    $this->actingAs($intruder)
        ->get("/inspiration/moodboards/{$board->id}")
        ->assertNotFound();
});

it('returns 404 for an unknown moodboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/inspiration/moodboards/999999')
        ->assertNotFound();
});

it('falls back to the remote image url when the thumbnail download failed', function () {
    $user = User::factory()->create();
    [$board] = moodboardForProject($user);

    $image = moodboardSavedImage($user, $board, [
        'thumb_path' => null,
        'full_path' => null,
        'image_url' => 'https://cdn.example.com/original.jpg',
    ]);

    $this->actingAs($user)
        ->get("/inspiration/moodboards/{$board->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.0.id', $image->id)
            ->where('items.0.thumb_url', 'https://cdn.example.com/original.jpg')
            ->where('items.0.image_url', 'https://cdn.example.com/original.jpg')
            ->where('items.0.full_url', null)
            ->where('items.0.download_status', SavedImage::STATUS_THUMB));
});

it('resolves the stored thumbnail and full size urls from the disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    [$board] = moodboardForProject($user);

    $thumbPath = 'inspiration/'.$user->id.'/wallhaven/abc-1.thumb.jpg';
    $fullPath = 'inspiration/'.$user->id.'/wallhaven/abc-1.full.jpg';

    $image = moodboardSavedImage($user, $board, [
        'thumb_path' => $thumbPath,
        'full_path' => $fullPath,
        'image_url' => 'https://cdn.example.com/original.jpg',
        'download_status' => SavedImage::STATUS_FULL,
    ]);

    Storage::disk('local')->put($thumbPath, 'thumb-bytes');
    Storage::disk('local')->put($fullPath, 'full-bytes');

    $this->actingAs($user)
        ->get("/inspiration/moodboards/{$board->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.0.thumb_url', Storage::disk('local')->url($thumbPath))
            ->where('items.0.full_url', Storage::disk('local')->url($fullPath))
            ->where('items.0.download_status', SavedImage::STATUS_FULL));
});

it('leaves the full url null when the full download has not happened', function () {
    $user = User::factory()->create();
    [$board] = moodboardForProject($user);

    $image = moodboardSavedImage($user, $board, [
        'thumb_path' => null,
        'full_path' => null,
    ]);

    $this->actingAs($user)
        ->get("/inspiration/moodboards/{$board->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.0.id', $image->id)
            ->where('items.0.full_url', null));
});

it('requires authentication to view a moodboard', function () {
    $user = User::factory()->create();
    [$board] = moodboardForProject($user);

    $this->get("/inspiration/moodboards/{$board->id}")->assertRedirect('/login');
});
