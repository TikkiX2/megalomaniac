<?php

namespace App\Console\Commands;

use App\Feed\DigestAgent;
use App\Feed\FeedRanker;
use App\Models\User;
use Illuminate\Console\Command;

class DigestFeedsCommand extends Command
{
    protected $signature = 'feed:digest {--user= : Generate the digest for a specific user id}';

    protected $description = 'Generate the daily AI digest of the user feed';

    public function handle(DigestAgent $digestAgent, FeedRanker $ranker): int
    {
        if ($userId = $this->option('user')) {
            $user = User::find($userId);

            if (! $user) {
                $this->error('User not found.');

                return self::FAILURE;
            }

            $items = $ranker->top($user, 10);

            if ($items->isEmpty()) {
                $this->info('No items to digest.');

                return self::SUCCESS;
            }

            $digestAgent->generate($user, $items);
            $this->info('Digest generated.');

            return self::SUCCESS;
        }

        $count = $digestAgent->generateDue();

        $this->info("Generated {$count} digests.");

        return self::SUCCESS;
    }
}
