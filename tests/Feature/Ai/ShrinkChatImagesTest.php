<?php

use App\Models\ChatAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function largeJpegBytes(int $width = 2400, int $height = 1800): string
{
    $image = imagecreatetruecolor($width, $height);

    for ($i = 0; $i < 400; $i++) {
        $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
        imagefilledrectangle($image, random_int(0, $width - 50), random_int(0, $height - 50), random_int(50, $width), random_int(50, $height), $color);
    }

    ob_start();
    imagejpeg($image, null, 95);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

test('the shrink command re-optimizes stored images and updates their size', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = 'ai-attachments/'.$user->id.'/big.jpg';
    $bytes = largeJpegBytes();

    Storage::disk('local')->put($path, $bytes);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'disk' => 'local',
        'path' => $path,
        'size' => strlen($bytes),
    ]);

    $this->artisan('megalomaniac:shrink-chat-images', ['--user' => $user->id])->assertSuccessful();

    $stored = Storage::disk('local')->get($path);
    $info = getimagesizefromstring($stored);

    expect(strlen($stored))->toBeLessThan(strlen($bytes))
        ->and($attachment->refresh()->size)->toBe(strlen($stored))
        ->and($info[0])->toBeLessThanOrEqual(1600);
});

test('the dry run does not modify the files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = 'ai-attachments/'.$user->id.'/big.jpg';
    $bytes = largeJpegBytes();

    Storage::disk('local')->put($path, $bytes);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'image',
        'disk' => 'local',
        'path' => $path,
        'size' => strlen($bytes),
    ]);

    $this->artisan('megalomaniac:shrink-chat-images', ['--user' => $user->id, '--dry-run' => true])->assertSuccessful();

    expect(Storage::disk('local')->get($path))->toBe($bytes)
        ->and($attachment->refresh()->size)->toBe(strlen($bytes));
});
