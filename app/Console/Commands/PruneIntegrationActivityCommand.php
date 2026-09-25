<?php

namespace App\Console\Commands;

use App\Models\IntegrationActionLog;
use Illuminate\Console\Command;

class PruneIntegrationActivityCommand extends Command
{
    protected $signature = 'integrations:prune-activity';

    protected $description = 'Prune integration activity logs past retention';

    public function handle(): int
    {
        $days = (int) config('integrations.retention_days');

        $count = IntegrationActionLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$count} activity logs.");

        return self::SUCCESS;
    }
}
