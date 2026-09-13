<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionBillingStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
