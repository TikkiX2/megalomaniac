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
 * Downloads and stores the local thumbnail for a freshly saved image.
 *
 * A failure is non-fatal and never touches `download_status`: it logs a
 * warning and leaves `thumb_path` null so the frontend keeps using the remote
 * `image_url` as the visual fallback (Review Focus 2). `$tries = 1` because the
 * user retries from the UI.
 */
class DownloadThumbJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public SavedImage $saved,
        public ?string $thumbnailUrl = null,
    ) {}

    public function handle(): void
    {
        $url = $this->thumbnailUrl ?? $this->saved->image_url;
        $path = sprintf(
            'inspiration/%d/%s/%s.thumb.%s',
            $this->saved->user_id,
            $this->saved->source,
            $this->saved->source_id,
            self::extension($url),
        );

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Thumbnail download returned HTTP {$response->status()}.");
            }

            Storage::disk(config('inspiration.disk'))->put($path, $response->body());

            $this->saved->update(['thumb_path' => $path]);
        } catch (Throwable $exception) {
            Log::warning('Inspiration thumbnail download failed.', [
                'saved_image_id' => $this->saved->id,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
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
