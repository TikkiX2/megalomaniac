<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Jobs\RasterizePdfPagesJob;
use App\Models\HealthStudy;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StudyImportService
{
    public function __construct(
        private PdfService $pdfService,
    ) {}

    /**
     * Inicia el pipeline de importación de un PDF asociado a un estudio.
     *
     * @param  Media  $pdfMedia  Registro del PDF en la colección `attachments`.
     */
    public function import(HealthStudy $study, Media $pdfMedia): void
    {
        // Validaciones básicas
        if ($pdfMedia->mime_type !== 'application/pdf') {
            throw new \InvalidArgumentException('El archivo debe ser un PDF.');
        }

        // Límite de páginas (configurable)
        $pages = $this->pdfService->countPages($pdfMedia->getPath());

        $maxPages = (int) config('health.pdf.max_pages', 50);

        if ($pages > $maxPages) {
            throw new \RuntimeException("El PDF supera el límite de {$maxPages} páginas.");
        }

        // Dispara el job de rasterizado. A su vez, el job dispara la extracción.
        RasterizePdfPagesJob::dispatch($study->id, $pdfMedia->id);
    }
}
