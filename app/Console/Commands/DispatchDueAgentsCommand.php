<?php

namespace App\Console\Commands;

use App\Ai\Agents\AgentScheduler;
use Illuminate\Console\Command;

class DispatchDueAgentsCommand extends Command
{
    protected $signature = 'agents:dispatch-due';

    protected $description = 'Dispatch background agent runs that are due';

    public function handle(AgentScheduler $scheduler): int
    {
        $count = $scheduler->dispatchDue();

        $this->info("Dispatched {$count} agent runs.");

        return self::SUCCESS;
    }
}
