<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketplaceTransactionStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
