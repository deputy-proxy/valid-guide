<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityReportReason: string
{
    case Inappropriate = 'inappropriate';
    case Privacy = 'privacy';
    case MisleadingValidationClaim = 'misleading_validation_claim';
    case Other = 'other';
}
