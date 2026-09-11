<?php

declare(strict_types=1);

namespace App\Enums;

enum ImprovementGuidanceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Dismissed = 'dismissed';
}
