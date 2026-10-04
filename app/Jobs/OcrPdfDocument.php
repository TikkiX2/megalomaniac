<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\PdfOcrAgent;
use App\Ai\Enums\AiScope;
use App\Ai\Support\AiRequestExecutor;
use App\Ai\Support\AiScopeResolver;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\User;
use App\Services\Health\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $outDir = sys_get_temp_dir().'/ocr_'.uniqid('', true);

        try {
            $raw = Storage::disk($attachment->disk)->get($attachment->path);

            if ($raw === null || $raw === '') {
                return;
            }

            file_put_contents($temp, $raw);

            mkdir($outDir, 0755, true);

            $images = $pdfService->rasterizePages($temp, $outDir, dpi: 150);

            if (empty($images)) {
                $attachment->forceFill(['status' => 'indexed', 'error' => 'PDF sin páginas rasterizables.', 'meta' => $meta])->save();

                return;
            }

            // StoredImage lee con Storage::disk(), que resuelve rutas relativas
            // al disco local; las páginas temporales de /tmp no existen ahí.
            // Se copian al disco local bajo una carpeta efímera del usuario.
            $workPrefix = 'ocr-work/'.Str::uuid7();
            $storedImages = [];

            foreach (array_values($images) as $index => $imagePath) {
                $relative = $workPrefix.'/'.sprintf('page-%02d.jpg', $index + 1);
                Storage::disk('local')->put($relative, (string) file_get_contents($imagePath));
                $storedImages[] = new StoredImage($relative, 'local');
            }

            if ($storedImages === []) {
                $attachment->forceFill(['status' => 'indexed', 'error' => 'PDF sin páginas rasterizables.', 'meta' => $meta])->save();

                return;
            }

            $this->transcribe($attachment, $user, $storedImages, $meta, $resolver, $executor);
        } catch (Throwable $exception) {
            report($exception);
            $attachment->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)])->save();
        } finally {
            Storage::disk('local')->deleteDirectory(($workPrefix ?? ''));
            @unlink($temp);
            array_map('unlink', glob($outDir.'/*') ?: []);
            @rmdir($outDir);
        }
    }

    /**
     * @param  array<int, StoredImage>  $storedImages
     * @param  array<string, mixed>  $meta
     */
    private function transcribe(
        ChatAttachment $attachment,
        User $user,
        array $storedImages,
        array $meta,
        AiScopeResolver $resolver,
        AiRequestExecutor $executor,
    ): void {
        // Los archivos (PDFs escaneados) se transcriben con el provider
        // multimodal configurado en el scope surface:files, que el usuario
        // asigna en Ajustes → IA → Alcances. No se usa module:health para
        // no forzar modelos de visión en los chats de texto.
        $resolution = $resolver->resolve($user, AiScope::SurfaceFiles);

        if ($resolution->chain->isEmpty()) {
            $attachment->forceFill([
                'status' => 'failed',
                'error' => 'No hay un proveedor multimodal configurado para leer archivos (scope "surface:files").',
            ])->save();

            return;
        }

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

        // 41 páginas de un PDF superan cualquier contexto; el OCR puede
        // devolver una transcripción parcial. Se corta el texto excesivo.
        $meta['ocr_text'] = $ocrText;
        $meta['ocr_at'] = now()->toIso8601String();

        $this->indexOcrText($attachment, $ocrText);

        $attachment->forceFill(['status' => 'indexed', 'error' => null, 'meta' => $meta])->save();
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
