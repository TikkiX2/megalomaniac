<?php

namespace App\Ai\Documents;

use Illuminate\Support\Facades\Storage;

class TextExtractor
{
    public function extract(string $disk, string $path): string
    {
        $raw = (string) Storage::disk($disk)->get($path);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        return $raw;
    }
}
