<?php

declare(strict_types=1);

namespace App\Jobs\Inspiration;

use App\Inspiration\Support\StoredImagePath;
use App\Inspiration\Support\UrlSafety;
use App\Models\SavedImage;
use GuzzleHttp\TransferStats;
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
 * user retries from the UI. Redirects are followed (max 3) but a chain that
 * lands on a private/reserved host aborts the download.
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
        $path = StoredImagePath::thumb($this->saved, $url);

        try {
            $effectiveUri = null;

            $response = Http::timeout(30)
                ->withOptions([
                    'allow_redirects' => ['max' => 3],
                    'on_stats' => static function (TransferStats $stats) use (&$effectiveUri): void {
                        $effectiveUri = (string) $stats->getEffectiveUri();
                    },
                ])
                ->get($url);

            if (UrlSafety::redirectTargetIsPrivate($effectiveUri)) {
                throw new RuntimeException('Thumbnail download redirect landed on a private host.');
            }

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
}
