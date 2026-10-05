<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inspiration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspiration\StoreSavedImageRequest;
use App\Inspiration\Exceptions\DownloadQuotaExceededException;
use App\Inspiration\Exceptions\DuplicateSavedImageException;
use App\Inspiration\InspirationSaveService;
use App\Models\Moodboard;
use App\Models\SavedImage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Save / remove / download endpoints for the inspiration surface.
 *
 * Route bindings resolve `saved_image` by id; ownership is enforced here so a
 * foreign row is indistinguishable from a missing one (404).
 */
class SavedImageController extends Controller
{
    public function __construct(private readonly InspirationSaveService $saves) {}

    public function store(StoreSavedImageRequest $request): JsonResponse
    {
        $user = $request->user();
        $board = $this->boardFor($request, $user);

        try {
            $saved = $this->saves->save($user, $request->validated(), $board);
        } catch (DuplicateSavedImageException $exception) {
            $existing = $exception->existing->loadMissing('moodboard');

            return response()->json([
                'message' => $exception->getMessage(),
                'existing_moodboard_id' => $existing->moodboard_id,
                'existing_moodboard_name' => $existing->moodboard?->name,
            ], 409);
        }

        return response()->json([
            'saved_image' => [
                'id' => $saved->id,
                'moodboard_id' => $saved->moodboard_id,
                'download_status' => $saved->download_status,
                'thumb_path' => $saved->thumb_path,
            ],
        ], 201);
    }

    public function destroy(Request $request, SavedImage $savedImage): Response
    {
        $this->authorizeOwner($request->user(), $savedImage);

        $savedImage->delete();

        return response()->noContent();
    }

    public function download(Request $request, SavedImage $savedImage): JsonResponse
    {
        $user = $request->user();
        $this->authorizeOwner($user, $savedImage);

        if ($savedImage->download_status === SavedImage::STATUS_FULL) {
            return response()->json(['status' => SavedImage::STATUS_FULL]);
        }

        try {
            $this->saves->requestFullDownload($user, $savedImage);
        } catch (DownloadQuotaExceededException $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        }

        return response()->json(['status' => 'queued'], 202);
    }

    /**
     * Resolve the target board: an explicit owned moodboard or the Inbox.
     */
    private function boardFor(StoreSavedImageRequest $request, User $user): Moodboard
    {
        $moodboardId = $request->validated('moodboard_id');

        if ($moodboardId !== null) {
            return Moodboard::query()->forUser($user)->findOrFail((int) $moodboardId);
        }

        return $this->saves->ensureInbox($user);
    }

    private function authorizeOwner(User $user, SavedImage $savedImage): void
    {
        abort_unless($savedImage->user_id === $user->id, 404);
    }
}
