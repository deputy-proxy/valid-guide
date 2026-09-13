<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationEventType;
use App\Events\WorkflowEventRecorded;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\DisputeReviewer;
use App\Models\EvaluationDecision;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\Validation;
use App\Models\ValidationTrustMonitor;
use App\Services\WorkflowNotificationService;

final class DispatchWorkflowNotification
{
    public function handle(WorkflowEventRecorded $event): void
    {
        $service = app(WorkflowNotificationService::class);

        match ($event->event) {
            'auditor_assignment.created' => $event->auditable instanceof AuditorAssignment ? $service->auditorAssignmentCreated($event->auditable) : null,
            'auditor_evaluation.submitted' => $event->auditable instanceof AuditorEvaluation ? $service->auditorEvaluationSubmitted($event->auditable) : null,
            'evaluation.decision_recorded' => $event->auditable instanceof EvaluationDecision ? $service->evaluationDecisionRecorded($event->auditable) : null,
            'validation.issued' => $event->auditable instanceof Validation ? $service->validationIssued($event->auditable) : null,
            'validation.status_changed' => $event->auditable instanceof Validation ? $service->validationStatusChanged($event->auditable) : null,
            'report.delivered' => $event->auditable instanceof Report ? $service->reportDelivered($event->auditable) : null,
            'clarification.submitted' => $event->auditable instanceof ClarificationRequest ? $service->clarificationSubmitted($event->auditable) : null,
            'clarification.answered' => $event->auditable instanceof ClarificationRequest ? $service->clarificationAnswered($event->auditable) : null,
            'dispute.submitted' => $event->auditable instanceof Dispute ? $service->disputeSubmitted($event->auditable) : null,
            'dispute.reviewer_assigned' => $this->notifyDisputeReviewer($event->auditable, $service),
            'dispute.resolved' => $event->auditable instanceof Dispute ? $service->disputeResolved($event->auditable) : null,
            'validation_trust_monitor.trust_state_changed' => $event->auditable instanceof ValidationTrustMonitor ? $service->trustStateChanged($event->auditable) : null,
            'validation_trust_monitor.failed', 'validation_trust_monitor.invalid' => $event->auditable instanceof ValidationTrustMonitor ? $service->trustMonitoringFailed($event->auditable) : null,
            'validation_trust_monitor.stale' => $event->auditable instanceof ValidationTrustMonitor ? $service->trustMonitoringStale($event->auditable) : null,
            'validation_trust_monitor.recovered' => $event->auditable instanceof ValidationTrustMonitor ? $service->trustMonitoringRecovered($event->auditable) : null,
            'subscription.created' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionCreated) : null,
            'subscription.activated' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionActivated) : null,
            'subscription.renewed' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionRenewed) : null,
            'subscription.payment_failed' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionPaymentFailed) : null,
            'subscription.recovered' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionRecovered) : null,
            'subscription.refunded' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionRefunded) : null,
            'subscription.cancelled' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionCancelled) : null,
            'subscription.expired' => $event->auditable instanceof Subscription ? $service->subscriptionLifecycleChanged($event->auditable, NotificationEventType::SubscriptionExpired) : null,
            default => null,
        };
    }

    private function notifyDisputeReviewer(mixed $auditable, WorkflowNotificationService $service): void
    {
        if (! $auditable instanceof DisputeReviewer) {
            return;
        }

        $dispute = $auditable->dispute;
        $reviewer = $auditable->reviewer;

        if ($dispute !== null && $reviewer !== null) {
            $service->disputeReviewerAssigned($dispute, $reviewer, (int) $auditable->getKey());
        }
    }
}
