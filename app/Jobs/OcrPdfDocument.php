<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\PdfOcrAgent;
use App\Ai\Enums\AiScope;
use App\Ai\Support\AiRequestExecutor;
use App\Ai\Support\AiScopeResolver;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Services\Health\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\StoredImage;
use Throwable;

/**
 * Procesa un PDF sin capa de texto extraíble: lo rasteriza con pdftoppm y
 * pide al modelo multimodal que transcriba (OCR) el contenido. El texto
 * resultante se guarda en `meta.ocr_text` (para reutilizarlo sin volver a
 * pagar la llamada) y se indexa en chunks FTS para el asesor.
 */
class OcrPdfDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public string $attachmentId) {}

    public function handle(
        AiScopeResolver $resolver,
        AiRequestExecutor $executor,
        PdfService $pdfService,
    ): void {
        $attachment = ChatAttachment::find($this->attachmentId);

        if ($attachment === null || $attachment->kind !== 'document') {
            return;
        }

        $meta = $attachment->meta ?? [];

        // Caché: si ya hay texto OCR persistido, solo re-indexarlo.
        if (isset($meta['ocr_text']) && is_string($meta['ocr_text']) && $meta['ocr_text'] !== '') {
            $this->indexOcrText($attachment, $meta['ocr_text']);
            $attachment->forceFill(['status' => 'indexed', 'error' => null, 'meta' => $meta])->save();

            return;
        }

        $user = $attachment->user;

        if ($user === null) {
            return;
        }

        $temp = tempnam(sys_get_temp_dir(), 'ocr_');

        if ($temp === false) {
            return;
        }

        try {
            $disk = $attachment->disk;
            $path = $attachment->path;

            $raw = Storage::disk($disk)->get($path);

            if ($raw === null || $raw === '') {
                return;
            }

            file_put_contents($temp, $raw);

            $outDir = sys_get_temp_dir().'/ocr_'.uniqid('', true);
            mkdir($outDir, 0755, true);

            $images = $pdfService->rasterizePages($temp, $outDir, dpi: 150);

            if (empty($images)) {
                $attachment->forceFill(['status' => 'indexed', 'error' => 'PDF sin páginas rasterizables.', 'meta' => $meta])->save();

                return;
            }

            $storedImages = array_map(
                fn (string $imagePath): StoredImage => new StoredImage($imagePath, 'local'),
                $images,
            );

            $resolution = $resolver->resolve($user, AiScope::ModuleHealth);

            $raw = $executor->execute($user, $resolution, function (string $key, string $model, $provider) use ($storedImages, $attachment): string {
                $agent = new PdfOcrAgent(fileName: $attachment->original_name);

                return (string) $agent->prompt(
                    'Transcribí el texto de las páginas adjuntas.',
                    attachments: $storedImages,
                    provider: $key,
                    model: $model ?: $provider->model,
                    timeout: 240,
                );
            });

            $ocrText = trim((string) $raw);

            if ($ocrText === '') {
                $attachment->forceFill(['status' => 'indexed', 'error' => 'OCR sin texto legible.', 'meta' => $meta])->save();

                return;
            }

            $meta['ocr_text'] = $ocrText;
            $meta['ocr_at'] = now()->toIso8601String();

            $this->indexOcrText($attachment, $ocrText);

            $attachment->forceFill(['status' => 'indexed', 'error' => null, 'meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);
            $attachment->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)])->save();
        } finally {
            @unlink($temp);
            if (isset($outDir)) {
                array_map('unlink', glob($outDir.'/*') ?: []);
                @rmdir($outDir);
            }
        }
    }

    private function indexOcrText(ChatAttachment $attachment, string $text): void
    {
        $attachment->chunks()->delete();

        $normalized = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');

        if ($normalized === '') {
            return;
        }

        $size = 1000;
        $overlap = 200;
        $offset = 0;
        $length = mb_strlen($normalized);
        $position = 0;

        while ($offset < $length && $position < 2000) {
            ChatDocumentChunk::create([
                'attachment_id' => $attachment->id,
                'position' => $position,
                'content' => mb_substr($normalized, $offset, $size),
            ]);
            $offset += max(1, $size - $overlap);
            $position++;
        }
    }
}
