<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
