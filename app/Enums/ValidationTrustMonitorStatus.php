<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationTrustMonitorStatus: string
{
    case Active = 'active';
    case Failed = 'failed';
    case Stale = 'stale';
    case Invalid = 'invalid';
    case Cancelled = 'cancelled';
}
