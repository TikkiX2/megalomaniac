<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\StorePhotoRequest;
use App\Models\JournalMoment;
use App\Services\Journal\JournalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    public function __construct(private readonly JournalAccess $access) {}

    public function store(StorePhotoRequest $request, JournalMoment $moment): RedirectResponse
    {
        $this->authorize('update', $moment);
        $this->access->assertUnlockedForPrivate($request, $moment->is_private);

        $moment->addMediaFromRequest('file')->toMediaCollection('photos', 'local');

        return back()->with('success', 'Foto agregada.');
    }

    public function show(Request $request, JournalMoment $moment, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $moment);
        $this->ensurePhotoBelongsToMoment($moment, $media);

        if ($moment->is_private && ! $this->access->unlocked($request)) {
            abort(423);
        }

        return response()->file($media->getPath());
    }

    public function destroy(Request $request, JournalMoment $moment, Media $media): RedirectResponse
    {
        $this->authorize('update', $moment);
        $this->ensurePhotoBelongsToMoment($moment, $media);
        $this->access->assertUnlockedForPrivate($request, $moment->is_private);

        $media->delete();

        return back()->with('success', 'Foto eliminada.');
    }

    private function ensurePhotoBelongsToMoment(JournalMoment $moment, Media $media): void
    {
        abort_unless(
            $media->model_id === $moment->id && $media->model_type === JournalMoment::class,
            404,
        );
    }
}
