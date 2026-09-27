<?php

namespace App\Ai\Memory;

class MemoryCatalog
{
    public static function hashContent(string $content): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($content)));

        return hash('sha256', $normalized ?? $content);
    }
}
