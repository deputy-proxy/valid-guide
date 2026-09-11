<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WorkflowEventRecorded;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\DisputeReviewer;
use App\Models\EvaluationDecision;
use App\Models\Report;
use App\Models\Validation;
use App\Services\WorkflowNotificationService;

final class DispatchWorkflowNotification
{
    public function handle(WorkflowEventRecorded $event): void
    {
        $service = app(WorkflowNotificationService::class);

        match ($event->event) {
            'auditor_assignment.created' => $event->auditable instanceof AuditorAssignment
                ? $service->auditorAssignmentCreated($event->auditable)
                : null,
            'auditor_evaluation.submitted' => $event->auditable instanceof AuditorEvaluation
                ? $service->auditorEvaluationSubmitted($event->auditable)
                : null,
            'evaluation.decision_recorded' => $event->auditable instanceof EvaluationDecision
                ? $service->evaluationDecisionRecorded($event->auditable)
                : null,
            'validation.issued' => $event->auditable instanceof Validation
                ? $service->validationIssued($event->auditable)
                : null,
            'report.delivered' => $event->auditable instanceof Report
                ? $service->reportDelivered($event->auditable)
                : null,
            'clarification.submitted' => $event->auditable instanceof ClarificationRequest
                ? $service->clarificationSubmitted($event->auditable)
                : null,
            'clarification.answered' => $event->auditable instanceof ClarificationRequest
                ? $service->clarificationAnswered($event->auditable)
                : null,
            'dispute.submitted' => $event->auditable instanceof Dispute
                ? $service->disputeSubmitted($event->auditable)
                : null,
            'dispute.reviewer_assigned' => $this->notifyDisputeReviewer($event->auditable, $service),
            'dispute.resolved' => $event->auditable instanceof Dispute
                ? $service->disputeResolved($event->auditable)
                : null,
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
