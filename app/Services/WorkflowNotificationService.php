<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationEventType;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\Report;
use App\Models\User;
use App\Notifications\WorkflowNotification;

final class WorkflowNotificationService
{
    public function auditorAssignmentCreated(AuditorAssignment $assignment): void
    {
        $assignment->loadMissing('evaluation.product', 'auditor');
        $productTitle = $assignment->evaluation?->product?->title ?? 'the assigned product';

        $this->send(
            $assignment->auditor,
            NotificationCategory::Assignment,
            NotificationEventType::AuditorAssignmentCreated,
            'New evaluation assignment',
            sprintf('You have been assigned to evaluate %s.', $productTitle),
            [
                'assignment_id' => $assignment->getKey(),
                'evaluation_id' => $assignment->evaluation_id,
            ],
        );
    }

    public function auditorEvaluationSubmitted(AuditorEvaluation $auditorEvaluation): void
    {
        $auditorEvaluation->loadMissing('evaluation.product', 'assignment');
        $this->sendToPlatformAdmins(
            NotificationCategory::Submission,
            NotificationEventType::AuditorEvaluationSubmitted,
            'Auditor evaluation submitted',
            'An Auditor has submitted an evaluation requiring platform review.',
            [
                'auditor_evaluation_id' => $auditorEvaluation->getKey(),
                'evaluation_id' => $auditorEvaluation->evaluation_id,
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

        $productTitle = $report->evaluation?->product?->title ?? 'your product';
        $this->sendToOrganization(
            $organization->getKey(),
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
        $request->loadMissing('organization');
        $this->sendToOrganization(
            $request->organization_id,
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

    public function disputeReviewerAssigned(Dispute $dispute, User $reviewer): void
    {
        $this->send(
            $reviewer,
            NotificationCategory::Dispute,
            NotificationEventType::DisputeReviewerAssigned,
            'Dispute review assigned',
            'You have been assigned to an independent dispute review.',
            ['dispute_id' => $dispute->getKey(), 'evaluation_id' => $dispute->evaluation_id],
        );
    }

    public function disputeResolved(Dispute $dispute): void
    {
        $dispute->loadMissing('organization');
        $this->sendToOrganization(
            $dispute->organization_id,
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
        User::query()->whereNotNull('platform_role')->each(
            fn (User $user): bool => tap($this->send($user, $category, $eventType, $title, $body, $context), fn (): true => true),
        );
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
        User::query()
            ->whereHas('organizations', function ($query) use ($organizationId): void {
                $query->whereKey($organizationId)->wherePivotIn('role', ['owner', 'admin', 'editor']);
            })
            ->each(fn (User $user): bool => tap($this->send($user, $category, $eventType, $title, $body, $context), fn (): true => true));
    }
}
