<?php

use App\Models\ChatAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('large uploaded images are downscaled before storing', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('foto.jpg', 2400, 1800);
    $originalSize = $file->getSize();

    $this->actingAs($user)
        ->post(route('ai.chat.attachments.store'), ['file' => $file])
        ->assertCreated();

    $attachment = ChatAttachment::query()->forUser($user)->images()->sole();
    $contents = Storage::disk('local')->get($attachment->path);
    $info = getimagesizefromstring($contents);

    expect($info)->not->toBeFalse()
        ->and($attachment->size)->toBe(strlen($contents))
        ->and($info[0])->toBeLessThanOrEqual(1600)
        ->and($info[1])->toBeLessThanOrEqual(1600)
        ->and($attachment->size)->toBeLessThan($originalSize);
});

test('small images keep a working stored file', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.attachments.store'), [
            'file' => UploadedFile::fake()->image('chica.png', 200, 200),
        ])
        ->assertCreated();

    $attachment = ChatAttachment::query()->forUser($user)->images()->sole();

    expect(Storage::disk('local')->exists($attachment->path))->toBeTrue()
        ->and(getimagesizefromstring(Storage::disk('local')->get($attachment->path)))->not->toBeFalse();
});
