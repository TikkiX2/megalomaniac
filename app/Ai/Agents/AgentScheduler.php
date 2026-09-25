<?php

namespace App\Ai\Agents;

use App\Jobs\RunAgentJob;
use App\Models\AgentDefinition;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;

class AgentScheduler
{
    public const INTERVALS = [
        '15m' => 15,
        '30m' => 30,
        '1h' => 60,
        '6h' => 360,
        '12h' => 720,
        'daily' => 1440,
        'weekly' => 10080,
    ];

    public function nextRunAt(AgentDefinition $definition): CarbonInterface
    {
        if ($definition->schedule_type === 'cron') {
            $next = (new CronExpression($definition->schedule_value))
                ->getNextRunDate(now(), 0, false, $definition->timezone ?: null);

            return CarbonImmutable::instance($next);
        }

        return now()->addMinutes(self::INTERVALS[$definition->schedule_value] ?? 60);
    }

    public function dispatchDue(): int
    {
        $dispatched = 0;

        AgentDefinition::query()
            ->due()
            ->chunkById(50, function ($definitions) use (&$dispatched): void {
                foreach ($definitions as $definition) {
                    $definition->forceFill(['next_run_at' => $this->nextRunAt($definition)])->save();

                    if ($definition->isRunning() || $definition->runsToday() >= $definition->max_runs_per_day) {
                        continue;
                    }

                    RunAgentJob::dispatch($definition->id, 'schedule');
                    $dispatched++;
                }
            });

        return $dispatched;
    }
}
