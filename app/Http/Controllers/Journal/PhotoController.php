<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\StorePhotoRequest;
use App\Models\JournalMoment;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    public function store(StorePhotoRequest $request, JournalMoment $moment): RedirectResponse
    {
        $this->authorize('update', $moment);

        $moment->addMediaFromRequest('file')->toMediaCollection('photos', 'local');

        return back()->with('success', 'Foto agregada.');
    }

    public function show(JournalMoment $moment, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $moment);
        $this->ensurePhotoBelongsToMoment($moment, $media);

        return response()->file($media->getPath());
    }

    public function destroy(JournalMoment $moment, Media $media): RedirectResponse
    {
        $this->authorize('update', $moment);
        $this->ensurePhotoBelongsToMoment($moment, $media);

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
