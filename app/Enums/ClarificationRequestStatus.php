<?php

declare(strict_types=1);

namespace App\Enums;

enum ClarificationRequestStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';
}
