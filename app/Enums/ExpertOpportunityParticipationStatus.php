<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpertOpportunityParticipationStatus: string
{
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Selected = 'selected';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
