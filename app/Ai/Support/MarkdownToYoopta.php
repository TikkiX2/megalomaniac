<?php

namespace App\Ai\Support;

use Illuminate\Support\Str;

class MarkdownToYoopta
{
    /**
     * Convert simple Markdown text into Yoopta editor blocks.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function convert(string $markdown): array
    {
        $blocks = [];
        $lines = preg_split('/\R/', trim($markdown)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$type, $text] = match (true) {
                (bool) preg_match('/^#{1,6}\s+(.*)$/', $line, $m) => ['heading', $m[1]],
                (bool) preg_match('/^[-*]\s+(.*)$/', $line, $m) => ['bulleted-list', $m[1]],
                (bool) preg_match('/^\d+[.)]\s+(.*)$/', $line, $m) => ['numbered-list', $m[1]],
                default => ['paragraph', $line],
            };

            $blocks[] = [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'children' => [['text' => $text]],
            ];
        }

        return $blocks;
    }
}
