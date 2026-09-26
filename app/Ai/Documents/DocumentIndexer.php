<?php

namespace App\Ai\Documents;

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DocumentIndexer
{
    protected const MAX_TEXT_LENGTH = 2_000_000;

    protected const MAX_CHUNKS = 2000;

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

                $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);

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

        while ($offset < $length && count($chunks) < self::MAX_CHUNKS) {
            $chunks[] = mb_substr($normalized, $offset, $size);
            $offset += max(1, $size - $overlap);
        }

        return $chunks;
    }
}
