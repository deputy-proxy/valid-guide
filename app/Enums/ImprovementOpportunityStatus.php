<?php

declare(strict_types=1);

namespace App\Enums;

enum ImprovementOpportunityStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Dismissed = 'dismissed';
    case Superseded = 'superseded';
}
