<?php

namespace App\Ai\Support;

use Illuminate\Support\Str;

class MarkdownToYoopta
{
    /**
     * Convert simple Markdown text into Yoopta v4 block data keyed by block id.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function convert(string $markdown): array
    {
        $blocks = [];
        $lines = preg_split('/\R/', trim($markdown)) ?: [];
        $order = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$type, $element, $text] = match (true) {
                (bool) preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) => self::heading($m[1], $m[2]),
                (bool) preg_match('/^[-*]\s+(.*)$/', $line, $m) => ['BulletedList', 'bulleted-list', $m[1]],
                (bool) preg_match('/^\d+[.)]\s+(.*)$/', $line, $m) => ['NumberedList', 'numbered-list', $m[1]],
                default => ['Paragraph', 'paragraph', $line],
            };

            $id = (string) Str::uuid();

            $blocks[$id] = [
                'id' => $id,
                'type' => $type,
                'value' => [[
                    'id' => (string) Str::uuid(),
                    'type' => $element,
                    'children' => [['text' => $text]],
                ]],
                'meta' => ['align' => 'left', 'depth' => 0, 'order' => $order],
            ];

            $order++;
        }

        return $blocks;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function heading(string $hashes, string $text): array
    {
        return match (strlen($hashes)) {
            1 => ['HeadingOne', 'heading-one', $text],
            2 => ['HeadingTwo', 'heading-two', $text],
            default => ['HeadingThree', 'heading-three', $text],
        };
    }
}
