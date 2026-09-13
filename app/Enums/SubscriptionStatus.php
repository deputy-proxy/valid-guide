<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PaymentFailed = 'payment_failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
