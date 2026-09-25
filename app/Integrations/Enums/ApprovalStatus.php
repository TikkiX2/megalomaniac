<?php

namespace App\Integrations\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Executed = 'executed';
    case Failed = 'failed';
}
