<?php

namespace App\Ai\Documents;

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DocumentIndexer
{
    public function index(ChatAttachment $attachment): void
    {
        try {
            DB::transaction(function () use ($attachment): void {
                $attachment->chunks()->delete();

                $extractor = ExtractorFactory::for($attachment->mime, pathinfo($attachment->path, PATHINFO_EXTENSION));

                if ($extractor === null) {
                    throw new RuntimeException('Tipo de documento no soportado.');
                }

                $text = trim($extractor->extract($attachment->disk, $attachment->path));

                if ($text === '') {
                    throw new RuntimeException('El documento no contiene texto extraíble.');
                }

                foreach ($this->chunks($text) as $position => $content) {
                    ChatDocumentChunk::create([
                        'attachment_id' => $attachment->id,
                        'position' => $position,
                        'content' => $content,
                    ]);
                }
            });

            $attachment->forceFill(['status' => 'indexed', 'error' => null])->save();
        } catch (Throwable $exception) {
            report($exception);
            $attachment->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)])->save();
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

        while ($offset < $length) {
            $chunks[] = mb_substr($normalized, $offset, $size);
            $offset += max(1, $size - $overlap);
        }

        return $chunks;
    }
}
