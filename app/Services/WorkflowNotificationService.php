<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\EvaluationDecision;
use App\Models\Report;
use App\Models\User;
use App\Models\Validation;
use App\Notifications\WorkflowNotification;

final class WorkflowNotificationService
{
    public function auditorAssignmentCreated(AuditorAssignment $assignment): void
    {
        $assignment->loadMissing('evaluation.product', 'auditor');
        $productTitle = $assignment->evaluation->product->title;

        $this->send(
            $assignment->auditor,
            NotificationCategory::Assignment,
            NotificationEventType::AuditorAssignmentCreated,
            'New evaluation assignment',
            sprintf('You have been assigned to evaluate %s.', $productTitle),
            ['assignment_id' => $assignment->getKey(), 'evaluation_id' => $assignment->evaluation_id],
        );
    }

    public function auditorEvaluationSubmitted(AuditorEvaluation $auditorEvaluation): void
    {
        $this->sendToPlatformAdmins(
            NotificationCategory::Submission,
            NotificationEventType::AuditorEvaluationSubmitted,
            'Auditor evaluation submitted',
            'An Auditor has submitted an evaluation requiring platform review.',
            ['auditor_evaluation_id' => $auditorEvaluation->getKey(), 'evaluation_id' => $auditorEvaluation->evaluation_id],
        );
    }

    public function evaluationDecisionRecorded(EvaluationDecision $decision): void
    {
        $decision->loadMissing('evaluation.request.organization');
        $organization = $decision->evaluation?->request?->organization;
        if ($organization === null) {
            return;
        }

        $this->sendToOrganization(
            (int) $organization->getKey(),
            NotificationCategory::Decision,
            NotificationEventType::EvaluationDecisionRecorded,
            'Evaluation decision recorded',
            'An authorized decision has been recorded for your evaluation.',
            ['evaluation_decision_id' => $decision->getKey(), 'evaluation_id' => $decision->evaluation_id],
        );
    }

    public function validationIssued(Validation $validation): void
    {
        $validation->loadMissing('evaluation.request.organization');
        $organization = $validation->evaluation?->request?->organization;
        if ($organization === null) {
            return;
        }

        $this->sendToOrganization(
            (int) $organization->getKey(),
            NotificationCategory::Decision,
            NotificationEventType::ValidationIssued,
            'Validation issued',
            'Your product has received a Valid.guide validation.',
            [
                'validation_id' => $validation->getKey(),
                'evaluation_id' => $validation->evaluation_id,
                'verification_identifier' => $validation->verification_identifier,
            ],
        );
    }

    public function reportDelivered(Report $report): void
    {
        $report->loadMissing('evaluation.request.organization', 'evaluation.product');
        $organization = $report->evaluation?->request?->organization;
        if ($organization === null) {
            return;
        }

        $productTitle = $report->evaluation->product->title;
        $this->sendToOrganization(
            (int) $organization->getKey(),
            NotificationCategory::Report,
            NotificationEventType::ReportDelivered,
            'Evaluation report available',
            sprintf('The evaluation report for %s is now available.', $productTitle),
            ['report_id' => $report->getKey(), 'evaluation_id' => $report->evaluation_id],
        );
    }

    public function clarificationSubmitted(ClarificationRequest $request): void
    {
        $this->sendToPlatformAdmins(
            NotificationCategory::Clarification,
            NotificationEventType::ClarificationSubmitted,
            'Clarification request submitted',
            'A creator has submitted a clarification request requiring review.',
            ['clarification_id' => $request->getKey(), 'evaluation_id' => $request->evaluation_id],
        );
    }

    public function clarificationAnswered(ClarificationRequest $request): void
    {
        $this->sendToOrganization(
            (int) $request->organization_id,
            NotificationCategory::Clarification,
            NotificationEventType::ClarificationAnswered,
            'Clarification answered',
            'Your clarification request has received an answer.',
            ['clarification_id' => $request->getKey(), 'evaluation_id' => $request->evaluation_id],
        );
    }

    public function disputeSubmitted(Dispute $dispute): void
    {
        $this->sendToPlatformAdmins(
            NotificationCategory::Dispute,
            NotificationEventType::DisputeSubmitted,
            'Formal dispute submitted',
            'A creator has submitted a formal dispute requiring review.',
            ['dispute_id' => $dispute->getKey(), 'evaluation_id' => $dispute->evaluation_id],
        );
    }

    public function disputeReviewerAssigned(Dispute $dispute, User $reviewer, int $reviewerAssignmentId): void
    {
        $this->send(
            $reviewer,
            NotificationCategory::Dispute,
            NotificationEventType::DisputeReviewerAssigned,
            'Dispute review assigned',
            'You have been assigned to an independent dispute review.',
            [
                'dispute_id' => $dispute->getKey(),
                'evaluation_id' => $dispute->evaluation_id,
                'dispute_reviewer_id' => $reviewerAssignmentId,
            ],
        );
    }

    public function disputeResolved(Dispute $dispute): void
    {
        $this->sendToOrganization(
            (int) $dispute->organization_id,
            NotificationCategory::Dispute,
            NotificationEventType::DisputeResolved,
            'Formal dispute resolved',
            'Your formal dispute has been resolved.',
            ['dispute_id' => $dispute->getKey(), 'evaluation_id' => $dispute->evaluation_id],
        );
    }

    /** @param array<string, int|string|null> $context */
    private function send(
        User $user,
        NotificationCategory $category,
        NotificationEventType $eventType,
        string $title,
        string $body,
        array $context,
    ): void {
        $user->notify(new WorkflowNotification($category, $eventType, $title, $body, $context));
    }

    /** @param array<string, int|string|null> $context */
    private function sendToPlatformAdmins(
        NotificationCategory $category,
        NotificationEventType $eventType,
        string $title,
        string $body,
        array $context,
    ): void {
        foreach (User::query()->whereNotNull('platform_role')->cursor() as $user) {
            $this->send($user, $category, $eventType, $title, $body, $context);
        }
    }

    /** @param array<string, int|string|null> $context */
    private function sendToOrganization(
        int $organizationId,
        NotificationCategory $category,
        NotificationEventType $eventType,
        string $title,
        string $body,
        array $context,
    ): void {
        $users = User::query()
            ->whereHas('organizations', function ($query) use ($organizationId): void {
                $query->whereKey($organizationId)->wherePivotIn('role', ['owner', 'admin', 'editor']);
            })
            ->cursor();

        foreach ($users as $user) {
            $this->send($user, $category, $eventType, $title, $body, $context);
        }
    }
}
