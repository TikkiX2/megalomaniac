<?php

namespace App\Ai\Documents;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PerplexityJsonExtractor
{
    protected const MAX_MESSAGE_LENGTH = 2000;

    /**
     * Extrae un chunk por mensaje de un export JSON de Perplexity.
     *
     * @return array<int, string>
     */
    public function extractChunks(string $disk, string $path): array
    {
        $raw = (string) Storage::disk($disk)->get($path);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new RuntimeException('El archivo JSON es inválido y no se pudo indexar.');
        }

        if (! isset($data['conversations']) || ! is_array($data['conversations'])) {
            throw new RuntimeException('El JSON no contiene conversaciones de Perplexity.');
        }

        $chunks = [];

        foreach ($data['conversations'] as $conversation) {
            if (! is_array($conversation)) {
                continue;
            }

            $title = isset($conversation['title']) && is_string($conversation['title']) && trim($conversation['title']) !== ''
                ? trim($conversation['title'])
                : 'Sin título';

            $conversationCreatedAt = $this->stringField($conversation, 'created_at', $this->stringField($conversation, 'updated_at'));

            $messages = $conversation['messages'] ?? [];

            if (! is_array($messages)) {
                continue;
            }

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $content = $message['content'] ?? '';

                if (is_array($content)) {
                    $content = implode("\n\n", array_filter($content, fn ($part): bool => is_string($part)));
                }

                if (! is_string($content)) {
                    continue;
                }

                $content = trim($content);

                if ($content === '') {
                    continue;
                }

                $role = isset($message['role']) && is_string($message['role']) && trim($message['role']) !== ''
                    ? trim($message['role'])
                    : 'desconocido';

                $createdAt = $this->stringField($message, 'created_at', $conversationCreatedAt);
                $header = "### {$title} [{$createdAt}] ({$role})";

                if (mb_strlen($content) <= self::MAX_MESSAGE_LENGTH) {
                    $chunks[] = "{$header}\n{$content}";

                    continue;
                }

                $parts = $this->splitMessage($content);
                $total = count($parts);

                foreach ($parts as $index => $part) {
                    $number = $index + 1;
                    $chunks[] = "{$header} (parte {$number}/{$total})\n{$part}";
                }
            }
        }

        if ($chunks === []) {
            throw new RuntimeException('El JSON no contiene conversaciones de Perplexity con texto extraíble.');
        }

        return $chunks;
    }

    public function extract(string $disk, string $path): string
    {
        return implode("\n\n", $this->extractChunks($disk, $path));
    }

    /**
     * Parte un mensaje largo por párrafos (doble salto de línea) en
     * fragmentos de como máximo MAX_MESSAGE_LENGTH caracteres.
     *
     * @return array<int, string>
     */
    protected function splitMessage(string $content, int $limit = self::MAX_MESSAGE_LENGTH): array
    {
        $paragraphs = preg_split("/\R\R+/u", $content) ?: [$content];
        $pieces = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                continue;
            }

            while (mb_strlen($paragraph) > $limit) {
                $pieces[] = mb_substr($paragraph, 0, $limit);
                $paragraph = trim(mb_substr($paragraph, $limit));
            }

            if ($paragraph !== '') {
                $pieces[] = $paragraph;
            }
        }

        $parts = [];
        $current = '';

        foreach ($pieces as $piece) {
            $candidate = $current === '' ? $piece : $current."\n\n".$piece;

            if (mb_strlen($candidate) <= $limit) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $parts[] = $current;
                }
                $current = $piece;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts === [] ? [$content] : $parts;
    }

    private function stringField(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? trim($data[$key]) : $default;
    }
}
