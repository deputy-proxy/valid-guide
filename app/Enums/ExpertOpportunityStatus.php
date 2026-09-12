<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpertOpportunityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
