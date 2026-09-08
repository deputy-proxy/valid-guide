<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeOutcome: string
{
    case Rejected = 'rejected';
    case Upheld = 'upheld';
    case PartiallyUpheld = 'partially_upheld';
    case ProcessFlawed = 'process_flawed';
}
