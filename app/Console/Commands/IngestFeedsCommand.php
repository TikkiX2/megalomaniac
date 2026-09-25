<?php

namespace App\Console\Commands;

use App\Feed\FeedIngestor;
use Illuminate\Console\Command;

class IngestFeedsCommand extends Command
{
    protected $signature = 'feed:ingest';

    protected $description = 'Ingest items from enabled feed sources';

    public function handle(FeedIngestor $ingestor): int
    {
        $created = $ingestor->ingestDue();

        $this->info("Ingested {$created} new feed items.");

        return self::SUCCESS;
    }
}
