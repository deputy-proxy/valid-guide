<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpertBoardMembershipStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Removed = 'removed';
}
