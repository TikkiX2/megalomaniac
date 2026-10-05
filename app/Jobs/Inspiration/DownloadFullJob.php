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
 * Downloads the full-size image on demand and marks the saved image as `full`.
 *
 * A failure flips `download_status` to `failed` so the UI can offer a retry.
 * `$tries = 1` keeps the retry in the user's hands. Redirects are followed
 * (max 3) but a chain that lands on a private/reserved host aborts the download.
 */
class DownloadFullJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public SavedImage $saved) {}

    public function handle(): void
    {
        $url = $this->saved->image_url;
        $path = StoredImagePath::full($this->saved, $url);

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
                throw new RuntimeException('Full download redirect landed on a private host.');
            }

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
}
