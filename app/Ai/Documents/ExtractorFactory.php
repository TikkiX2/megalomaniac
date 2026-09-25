<?php

namespace App\Ai\Documents;

class ExtractorFactory
{
    public static function for(string $mime, string $extension): TextExtractor|DocxExtractor|null
    {
        $mime = mb_strtolower(trim($mime));
        $extension = mb_strtolower(trim($extension));

        if (in_array($mime, ['text/plain', 'text/markdown'], true) || in_array($extension, ['txt', 'md'], true)) {
            return new TextExtractor;
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $extension === 'docx') {
            return new DocxExtractor;
        }

        return null;
    }
}
