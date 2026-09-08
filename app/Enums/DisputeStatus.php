<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
