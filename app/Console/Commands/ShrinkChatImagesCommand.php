<?php

namespace App\Console\Commands;

use App\Ai\Support\ChatImageOptimizer;
use App\Models\ChatAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ShrinkChatImagesCommand extends Command
{
    protected $signature = 'megalomaniac:shrink-chat-images
        {--user= : Only shrink attachments of this user id}
        {--dry-run : Report the projected savings without touching the files}';

    protected $description = 'Re-optimize stored chat images in place so the conversation history re-sent on every turn stays small';

    public function handle(ChatImageOptimizer $optimizer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = ChatAttachment::query()->images();

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $processed = 0;
        $saved = 0;

        $query->orderBy('id')->chunkById(50, function ($attachments) use ($optimizer, $dryRun, &$processed, &$saved): void {
            foreach ($attachments as $attachment) {
                $disk = Storage::disk($attachment->disk);

                if (! $disk->exists($attachment->path)) {
                    continue;
                }

                $absolute = $disk->path($attachment->path);
                $before = (int) $disk->size($attachment->path);

                if ($dryRun) {
                    $result = $optimizer->optimize($absolute);

                    if ($result === null || $result['size'] >= $before) {
                        continue;
                    }

                    $processed++;
                    $saved += $before - $result['size'];

                    continue;
                }

                if (! $optimizer->optimizeInPlace($absolute)) {
                    continue;
                }

                $after = (int) $disk->size($attachment->path);
                $attachment->update(['size' => $after]);

                $processed++;
                $saved += max(0, $before - $after);
            }
        });

        $this->info(sprintf(
            '%s %d images · %s saved',
            $dryRun ? 'Would shrink' : 'Shrunk',
            $processed,
            $this->humanBytes($saved),
        ));

        return self::SUCCESS;
    }

    protected function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
