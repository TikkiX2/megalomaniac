<?php

namespace App\Ai\Documents;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DocxExtractor
{
    protected const MAX_XML_BYTES = 20 * 1024 * 1024;

    public function extract(string $disk, string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open(Storage::disk($disk)->path($path)) !== true) {
            throw new RuntimeException('No se pudo abrir el DOCX.');
        }

        $stat = $zip->statName('word/document.xml');

        if ($stat === false) {
            $zip->close();

            throw new RuntimeException('DOCX sin word/document.xml.');
        }

        if (($stat['size'] ?? 0) > self::MAX_XML_BYTES) {
            $zip->close();

            throw new RuntimeException('El contenido del DOCX supera el límite de 20 MB.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('DOCX sin word/document.xml.');
        }

        $xml = str_replace(['</w:p>', '<w:br/>', '</w:tr>'], "\n", $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }
}
