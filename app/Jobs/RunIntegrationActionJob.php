<?php

namespace App\Jobs;

use App\Integrations\Actions\ExecutionContext;
use App\Integrations\IntegrationExecutor;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunIntegrationActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int $connectionId,
        public string $actionKey,
        public array $params,
        public ?int $approvalId = null,
        public string $source = 'approval',
    ) {}

    public function handle(IntegrationExecutor $executor): void
    {
        $connection = Connection::find($this->connectionId);

        if (! $connection) {
            return;
        }

        if ($this->approvalId && $approval = ApprovalRequest::find($this->approvalId)) {
            $executor->runApproval($approval);

            return;
        }

        $executor->execute(
            $connection,
            $this->actionKey,
            $this->params,
            ExecutionContext::forSchedule($connection->user),
        );
    }
}
