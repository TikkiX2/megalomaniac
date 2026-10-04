<?php

use App\Models\InspirationSetting;
use App\Models\Moodboard;
use App\Models\Project;
use App\Models\SavedImage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSavedImage(User $user, Moodboard $board, array $overrides = []): SavedImage
{
    return SavedImage::create(array_merge([
        'user_id' => $user->id,
        'moodboard_id' => $board->id,
        'source' => 'deviantart',
        'source_id' => 'abc-123',
        'title' => 'A portrait',
        'author' => 'someone',
        'author_url' => 'https://example.com/someone',
        'page_url' => 'https://example.com/page',
        'image_url' => 'https://example.com/full.jpg',
        'thumb_path' => 'inspiration/1/deviantart/abc-123.thumb.jpg',
        'tags' => ['portrait', 'dark'],
    ], $overrides));
}

it('creates an inbox moodboard and a moodboard for a personal project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['type' => 'personal']);

    $inbox = Moodboard::create([
        'user_id' => $user->id,
        'project_id' => null,
        'name' => 'Inbox',
    ]);

    $board = Moodboard::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => $project->name,
    ]);

    expect($inbox->project_id)->toBeNull()
        ->and($board->project)->toBeInstanceOf(Project::class)
        ->and($board->project->is($project))->toBeTrue()
        ->and(Moodboard::forUser($user->id)->count())->toBe(2);
});

it('casts saved image tags to an array and links it to its moodboard', function () {
    $user = User::factory()->create();
    $board = Moodboard::create(['user_id' => $user->id, 'project_id' => null, 'name' => 'Inbox']);

    $image = makeSavedImage($user, $board);

    expect($image->refresh()->tags)->toBeArray()->toBe(['portrait', 'dark'])
        ->and($image->download_status)->toBe(SavedImage::STATUS_THUMB)
        ->and($board->savedImages()->whereKey($image->id)->exists())->toBeTrue()
        ->and(SavedImage::forUser($user->id)->whereKey($image->id)->exists())->toBeTrue();
});

it('persists inspiration settings body through updateOrCreate', function () {
    $user = User::factory()->create();

    InspirationSetting::updateOrCreate(
        ['user_id' => $user->id],
        ['body' => [
            'enabled_sources' => ['deviantart', 'artstation'],
            'keys' => ['flickr' => 'secret'],
            'maturity' => true,
            'zerochan_ua' => 'Megalomaniac-ricky',
            'acknowledged_tier3' => ['pixiv'],
        ]],
    );

    $settings = InspirationSetting::find($user->id);

    expect($settings)->not->toBeNull()
        ->and($settings->body)->toBeArray()
        ->and($settings->body['maturity'])->toBeTrue()
        ->and($settings->body['acknowledged_tier3'])->toBe(['pixiv']);

    InspirationSetting::updateOrCreate(
        ['user_id' => $user->id],
        ['body' => ['maturity' => false]],
    );

    expect(InspirationSetting::count())->toBe(1)
        ->and(InspirationSetting::find($user->id)->body)->toBe(['maturity' => false]);
});

it('rejects duplicate saved images for the same user and source', function () {
    $user = User::factory()->create();
    $board = Moodboard::create(['user_id' => $user->id, 'project_id' => null, 'name' => 'Inbox']);

    makeSavedImage($user, $board);

    expect(fn () => makeSavedImage($user, $board))
        ->toThrow(QueryException::class);
});

it('rejects a second inbox moodboard for the same user', function () {
    $user = User::factory()->create();

    Moodboard::create(['user_id' => $user->id, 'project_id' => null, 'name' => 'Inbox']);

    expect(fn () => Moodboard::create(['user_id' => $user->id, 'project_id' => null, 'name' => 'Otro Inbox']))
        ->toThrow(QueryException::class);
});

it('rejects a second moodboard for the same project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['type' => 'personal']);

    Moodboard::create(['user_id' => $user->id, 'project_id' => $project->id, 'name' => $project->name]);

    expect(fn () => Moodboard::create(['user_id' => $user->id, 'project_id' => $project->id, 'name' => 'Duplicado']))
        ->toThrow(QueryException::class);
});
