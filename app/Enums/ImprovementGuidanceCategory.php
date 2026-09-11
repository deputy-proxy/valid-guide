<?php

declare(strict_types=1);

namespace App\Enums;

enum ImprovementGuidanceCategory: string
{
    case Content = 'content';
    case Structure = 'structure';
    case Delivery = 'delivery';
    case Evidence = 'evidence';
    case Accessibility = 'accessibility';
    case AudienceFit = 'audience_fit';
    case Assessment = 'assessment';
    case Other = 'other';
}
