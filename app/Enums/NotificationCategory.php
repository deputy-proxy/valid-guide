<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationCategory: string
{
    case Assignment = 'assignment';
    case Evidence = 'evidence';
    case Submission = 'submission';
    case Decision = 'decision';
    case Report = 'report';
    case Clarification = 'clarification';
    case Dispute = 'dispute';
    case System = 'system';
}
