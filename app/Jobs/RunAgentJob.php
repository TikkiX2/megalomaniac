<?php

namespace App\Jobs;

use App\Ai\Agents\AgentRunner;
use App\Models\AgentDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public int $definitionId,
        public string $triggeredBy = 'schedule',
    ) {}

    public function handle(AgentRunner $runner): void
    {
        $definition = AgentDefinition::find($this->definitionId);

        if (! $definition) {
            return;
        }

        $runner->run($definition, $this->triggeredBy);
    }
}
