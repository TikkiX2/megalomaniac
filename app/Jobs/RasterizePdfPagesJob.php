<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\HealthStudy;
use App\Services\Health\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RasterizePdfPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $studyId,
        public int $mediaId,
        public int $dpi = 150,
    ) {}

    public function handle(PdfService $pdfService): void
    {
        $study = HealthStudy::findOrFail($this->studyId);

        $media = $study->getMedia('attachments')
            ->where('id', $this->mediaId)
            ->firstOrFail();

        $pdfPath = $media->getPath();

        $outputDir = storage_path('app/temp/rasterize/'.$study->id.'/'.$media->id);

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
            Log::error('Unable to create rasterize directory', ['study' => $study->id, 'output' => $outputDir]);

            return;
        }

        $images = $pdfService->rasterizePages($pdfPath, $outputDir, $this->dpi);

        foreach ($images as $index => $imagePath) {
            $study->addMedia($imagePath)
                ->withCustomProperties(['page' => $index + 1])
                ->toMediaCollection('pages');
        }

        // Continue pipeline
        ExtractStudyResultsJob::dispatch($study->id, $media->id);
    }
}
