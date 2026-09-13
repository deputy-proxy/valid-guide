<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationTrustMonitorCadence: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function intervalMinutes(): int
    {
        return match ($this) {
            self::Hourly => 60,
            self::Daily => 1440,
            self::Weekly => 10080,
        };
    }
}
