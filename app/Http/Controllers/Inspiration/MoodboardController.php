<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inspiration;

use App\Http\Controllers\Controller;
use App\Models\Moodboard;
use App\Models\SavedImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Wall view for a single moodboard.
 *
 * Route binding is by id (`{moodboard}`) and ownership is enforced with an
 * explicit `where('user_id', ...)` scope, so a foreign board is
 * indistinguishable from a missing one (404) without a policy.
 */
class MoodboardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        $board = Moodboard::query()
            ->where('user_id', $user->id)
            ->with('project:id,name')
            ->findOrFail((int) $request->route('moodboard'));

        $items = SavedImage::query()
            ->where('moodboard_id', $board->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('inspiration/moodboard', [
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'project_name' => $board->project?->name,
            ],
            'items' => $items
                ->map(fn (SavedImage $image): array => $this->item($image))
                ->values()
                ->all(),
            'total' => $items->count(),
            'sources' => $items->countBy('source')->all(),
        ]);
    }

    /**
     * Inertia payload for one saved image.
     *
     * `thumb_url` always falls back to the remote `image_url` when no local
     * thumbnail is stored or the disk cannot resolve a public URL (Review
     * Focus 2: the grid always has something to render).
     *
     * @return array<string, mixed>
     */
    private function item(SavedImage $image): array
    {
        return [
            'id' => $image->id,
            'source' => $image->source,
            'source_id' => $image->source_id,
            'title' => $image->title,
            'author' => $image->author,
            'page_url' => $image->page_url,
            'tags' => $image->tags ?? [],
            'license' => $image->license,
            'maturity' => $image->maturity,
            'download_status' => $image->download_status,
            'thumb_url' => $this->storageUrl($image->thumb_path) ?? $image->image_url,
            'image_url' => $image->image_url,
            'full_url' => $this->storageUrl($image->full_path),
            'width' => $image->width,
            'height' => $image->height,
        ];
    }

    /**
     * Resolve a stored path to a public URL, or null when the file is missing
     * or the configured disk cannot produce one.
     */
    private function storageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            $disk = Storage::disk((string) config('inspiration.disk'));

            if (! $disk->exists($path)) {
                return null;
            }

            $url = $disk->url($path);
        } catch (Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }
}
