<?php

declare(strict_types=1);

namespace App\Jobs\Inspiration;

use App\Models\SavedImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Downloads the full-size image on demand and marks the saved image as `full`.
 *
 * A failure flips `download_status` to `failed` so the UI can offer a retry.
 * `$tries = 1` keeps the retry in the user's hands.
 */
class DownloadFullJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public SavedImage $saved) {}

    public function handle(): void
    {
        $url = $this->saved->image_url;
        $path = sprintf(
            'inspiration/%d/%s/%s.full.%s',
            $this->saved->user_id,
            $this->saved->source,
            $this->saved->source_id,
            self::extension($url),
        );

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Full download returned HTTP {$response->status()}.");
            }

            Storage::disk(config('inspiration.disk'))->put($path, $response->body());

            $this->saved->update([
                'full_path' => $path,
                'downloaded_at' => now(),
                'download_status' => SavedImage::STATUS_FULL,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Inspiration full download failed.', [
                'saved_image_id' => $this->saved->id,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            $this->saved->update(['download_status' => SavedImage::STATUS_FAILED]);
        }
    }

    /**
     * Derive a safe file extension from the remote URL, defaulting to jpg.
     */
    private static function extension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,5}$/', $extension) === 1 ? $extension : 'jpg';
    }
}
