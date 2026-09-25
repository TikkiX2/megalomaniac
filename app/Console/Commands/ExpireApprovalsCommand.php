<?php

namespace App\Console\Commands;

use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Console\Command;

class ExpireApprovalsCommand extends Command
{
    protected $signature = 'integrations:expire-approvals';

    protected $description = 'Expire pending integration approvals past their TTL';

    public function handle(): int
    {
        $count = ApprovalRequest::query()
            ->where('status', ApprovalStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => ApprovalStatus::Expired]);

        $this->info("Expired {$count} approvals.");

        return self::SUCCESS;
    }
}
