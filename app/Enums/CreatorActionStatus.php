<?php

declare(strict_types=1);

namespace App\Enums;

enum CreatorActionStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
