<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationEventType: string
{
    case AuditorAssignmentCreated = 'auditor_assignment_created';
    case AuditorEvaluationSubmitted = 'auditor_evaluation_submitted';
    case EvaluationDecisionRecorded = 'evaluation_decision_recorded';
    case ValidationIssued = 'validation_issued';
    case ReportDelivered = 'report_delivered';
    case ClarificationSubmitted = 'clarification_submitted';
    case ClarificationAnswered = 'clarification_answered';
    case DisputeSubmitted = 'dispute_submitted';
    case DisputeReviewerAssigned = 'dispute_reviewer_assigned';
    case DisputeResolved = 'dispute_resolved';
    case ImprovementOpportunityCreated = 'improvement_opportunity_created';
    case ImprovementOpportunityAssigned = 'improvement_opportunity_assigned';
    case ImprovementOpportunityCompleted = 'improvement_opportunity_completed';
    case ExpertOpportunityPublished = 'expert_opportunity_published';
    case ExpertOpportunityApplication = 'expert_opportunity_application';
    case ExpertOpportunitySelection = 'expert_opportunity_selection';
}
