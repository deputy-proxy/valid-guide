<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationEventType: string
{
    case AuditorAssignmentCreated = 'auditor_assignment_created';
    case AuditorEvaluationSubmitted = 'auditor_evaluation_submitted';
    case EvaluationDecisionRecorded = 'evaluation_decision_recorded';
    case ValidationIssued = 'validation_issued';
    case ValidationReactivated = 'validation_reactivated';
    case ValidationSuspended = 'validation_suspended';
    case ValidationRevoked = 'validation_revoked';
    case ValidationSuperseded = 'validation_superseded';
    case ReportDelivered = 'report_delivered';
    case ClarificationSubmitted = 'clarification_submitted';
    case ClarificationAnswered = 'clarification_answered';
    case DisputeSubmitted = 'dispute_submitted';
    case DisputeReviewerAssigned = 'dispute_reviewer_assigned';
    case DisputeResolved = 'dispute_resolved';
    case TrustStateChanged = 'trust_state_changed';
    case TrustMonitoringFailed = 'trust_monitoring_failed';
    case TrustMonitoringRecovered = 'trust_monitoring_recovered';
    case TrustMonitoringStale = 'trust_monitoring_stale';
    case ImprovementOpportunityCreated = 'improvement_opportunity_created';
    case ImprovementOpportunityAssigned = 'improvement_opportunity_assigned';
    case ImprovementOpportunityCompleted = 'improvement_opportunity_completed';
    case ExpertOpportunityPublished = 'expert_opportunity_published';
    case ExpertOpportunityApplication = 'expert_opportunity_application';
    case ExpertOpportunitySelection = 'expert_opportunity_selection';
    case SubscriptionCreated = 'subscription_created';
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionRenewed = 'subscription_renewed';
    case SubscriptionPaymentFailed = 'subscription_payment_failed';
    case SubscriptionRecovered = 'subscription_recovered';
    case SubscriptionRefunded = 'subscription_refunded';
    case SubscriptionCancelled = 'subscription_cancelled';
    case SubscriptionExpired = 'subscription_expired';
}
