<?php

namespace App\Ai\Documents;

use App\Jobs\OcrPdfDocument;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentIndexer
{
    protected const MAX_TEXT_LENGTH = 8_000_000;

    protected const MAX_CHUNKS = 12000;

    protected const INSERT_BATCH_SIZE = 500;

    public function index(ChatAttachment $attachment): void
    {
        $indexed = false;

        try {
            DB::transaction(function () use ($attachment, &$indexed): void {
                $attachment->chunks()->delete();

                $extension = mb_strtolower(pathinfo($attachment->path, PATHINFO_EXTENSION));
                $extractor = ExtractorFactory::for($attachment->mime, $extension, $attachment->disk, $attachment->path);

                if ($extractor === null) {
                    throw new RuntimeException('Tipo de documento no soportado.');
                }

                if (method_exists($extractor, 'extractChunks')) {
                    $this->guardRawSize($attachment);

                    $chunks = array_values($extractor->extractChunks($attachment->disk, $attachment->path));

                    if ($chunks === []) {
                        throw new RuntimeException('El documento no contiene texto extraíble.');
                    }

                    $this->storeChunks($attachment, array_slice($chunks, 0, self::MAX_CHUNKS));
                    $indexed = true;

                    return;
                }

                $indexed = $this->indexPlainText($attachment, $extractor, $extension);
            });

            if ($indexed) {
                $attachment->forceFill(['status' => 'indexed', 'error' => null])->save();
            }
        } catch (Throwable $exception) {
            report($exception);
            $attachment->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)])->save();
        }
    }

    /**
     * Los exports de Perplexity pueden acercarse a los 7M de caracteres:
     * el fichero raw no puede superar el tope indexable.
     */
    protected function guardRawSize(ChatAttachment $attachment): void
    {
        if (Storage::disk($attachment->disk)->size($attachment->path) > self::MAX_TEXT_LENGTH) {
            throw new RuntimeException('El documento supera el límite de 8 MB de texto indexable.');
        }
    }

    /**
     * Flujo clásico para extractores de texto plano (txt, md, json
     * genérico, docx, pdf). Devuelve false cuando el PDF se deriva a OCR.
     */
    protected function indexPlainText(ChatAttachment $attachment, TextExtractor|DocxExtractor|PdfTextExtractor $extractor, string $extension): bool
    {
        $text = trim($extractor->extract($attachment->disk, $attachment->path));

        if ($text === '') {
            // Los PDFs sin capa de texto (escaneados o cifrados) se
            // rasterizan y transcriben con el modelo multimodal vía OCR.
            if ($extension === 'pdf') {
                $attachment->forceFill(['status' => 'pending'])->save();
                OcrPdfDocument::dispatch($attachment->id);

                return false;
            }

            throw new RuntimeException('El documento no contiene texto extraíble.');
        }

        if ($extension === 'json' || mb_strtolower(trim($attachment->mime)) === 'application/json') {
            json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('El archivo JSON es inválido y no se pudo indexar.');
            }
        }

        $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);

        $this->storeChunks($attachment, $this->chunks($text));

        return true;
    }

    /**
     * @param  array<int, string>  $contents
     */
    protected function storeChunks(ChatAttachment $attachment, array $contents): void
    {
        $now = now();
        $rows = [];

        foreach ($contents as $position => $content) {
            $rows[] = [
                'attachment_id' => $attachment->id,
                'position' => $position,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= self::INSERT_BATCH_SIZE) {
                ChatDocumentChunk::insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            ChatDocumentChunk::insert($rows);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function chunks(string $text, int $size = 1000, int $overlap = 200): array
    {
        $normalized = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
        $chunks = [];
        $offset = 0;
        $length = mb_strlen($normalized);

        while ($offset < $length && count($chunks) < self::MAX_CHUNKS) {
            $chunks[] = mb_substr($normalized, $offset, $size);
            $offset += max(1, $size - $overlap);
        }

        return $chunks;
    }
}
