<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpertOpportunityType: string
{
    case Review = 'review';
    case Consultation = 'consultation';
    case Research = 'research';
    case Content = 'content';
}
