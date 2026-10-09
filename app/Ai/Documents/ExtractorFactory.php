<?php

namespace App\Ai\Documents;

use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractorFactory
{
    public static function for(string $mime, string $extension, ?string $disk = null, ?string $path = null): TextExtractor|DocxExtractor|PdfTextExtractor|PerplexityJsonExtractor|null
    {
        $mime = mb_strtolower(trim($mime));
        $extension = mb_strtolower(trim($extension));

        if (in_array($mime, ['text/plain', 'text/markdown', 'application/json'], true) || in_array($extension, ['txt', 'md', 'json'], true)) {
            if ($extension === 'json' && $disk !== null && $path !== null && self::isPerplexityExport($disk, $path)) {
                return new PerplexityJsonExtractor;
            }

            return new TextExtractor;
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $extension === 'docx') {
            return new DocxExtractor;
        }

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return new PdfTextExtractor;
        }

        return null;
    }

    protected static function isPerplexityExport(string $disk, string $path): bool
    {
        try {
            $raw = (string) Storage::disk($disk)->get($path);
        } catch (Throwable) {
            return false;
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        $data = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE
            && is_array($data)
            && isset($data['conversations'])
            && is_array($data['conversations']);
    }
}
