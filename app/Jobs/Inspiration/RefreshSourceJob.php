<?php

declare(strict_types=1);

namespace App\Jobs\Inspiration;

use App\Inspiration\SourceManager;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Background refresh of a stale inspiration cache entry.
 *
 * The explore/search flow serves any stored payload (fresh or stale, up to the
 * 12h cap) instantly and dispatches this job when the entry was expired, so a
 * cold or long-idle wall never blocks on the sequential remote fan-out.
 */
class RefreshSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly string $source,
        public readonly string $kind,
        public readonly string $query,
        public readonly int $page,
    ) {}

    public function handle(SourceManager $manager): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $manager->refresh($user, $this->source, $this->kind, $this->query, $this->page);
    }
}
