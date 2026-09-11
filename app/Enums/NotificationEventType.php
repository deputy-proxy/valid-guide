<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationEventType: string
{
    case AuditorAssignmentCreated = 'auditor_assignment_created';
    case AuditorEvaluationSubmitted = 'auditor_evaluation_submitted';
    case ReportDelivered = 'report_delivered';
    case ClarificationSubmitted = 'clarification_submitted';
    case ClarificationAnswered = 'clarification_answered';
    case DisputeSubmitted = 'dispute_submitted';
    case DisputeReviewerAssigned = 'dispute_reviewer_assigned';
    case DisputeResolved = 'dispute_resolved';
}
