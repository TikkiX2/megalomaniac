<?php

declare(strict_types=1);

namespace App\Ai\Documents;

use App\Services\Health\PdfService;
use Illuminate\Support\Facades\Storage;

class PdfTextExtractor
{
    public function extract(string $disk, string $path): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'pdf_');
        if ($temp === false) {
            return '';
        }

        try {
            file_put_contents($temp, (string) Storage::disk($disk)->get($path));

            if ((int) filesize($temp) === 0) {
                return '';
            }

            return (new PdfService)->extractText($temp);
        } catch (\Throwable) {
            // PDF corrupto o sin capa de texto extraíble.
            return '';
        } finally {
            @unlink($temp);
        }
    }
}
