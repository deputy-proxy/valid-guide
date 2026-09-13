<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImprovementOpportunityStatus;
use App\Enums\NotificationEventType;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\DisputeReviewer;
use App\Models\ImprovementOpportunity;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Validation;
use App\Models\ValidationTrustMonitor;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class WorkflowNotificationInbox
{
    /** @return Collection<int, array<string, mixed>> */
    public function for(User $user, int $limit = 50): Collection
    {
        /** @var Collection<int, array<string, mixed>> $notifications */
        $notifications = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (DatabaseNotification $notification): array {
                $data = $notification->data;
                $eventType = NotificationEventType::tryFrom((string) ($data['event_type'] ?? ''));

                return [
                    'id' => (string) $notification->getKey(),
                    'category' => (string) ($data['category'] ?? 'system'),
                    'event_type' => $eventType?->value,
                    'title' => (string) ($data['title'] ?? 'Notification'),
                    'body' => (string) ($data['body'] ?? ''),
                    'read' => $notification->read(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'stale' => $this->isStale($eventType, $data['context'] ?? []),
                    'action_url' => is_string($data['action_url'] ?? null) ? $data['action_url'] : null,
                ];
            });

        return $notifications;
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    private function isStale(?NotificationEventType $eventType, mixed $context): bool
    {
        if (is_array($context) === false) {
            return true;
        }

        return match ($eventType) {
            NotificationEventType::AuditorAssignmentCreated => $this->assignmentIsStale($context),
            NotificationEventType::AuditorEvaluationSubmitted => $this->auditorEvaluationIsStale($context),
            NotificationEventType::EvaluationDecisionRecorded,
            NotificationEventType::ValidationIssued,
            NotificationEventType::ReportDelivered,
            NotificationEventType::ClarificationSubmitted,
            NotificationEventType::ClarificationAnswered,
            NotificationEventType::DisputeSubmitted,
            NotificationEventType::DisputeReviewerAssigned,
            NotificationEventType::DisputeResolved,
            NotificationEventType::ImprovementOpportunityCreated,
            NotificationEventType::ImprovementOpportunityAssigned,
            NotificationEventType::ImprovementOpportunityCompleted,
            NotificationEventType::ExpertOpportunityPublished,
            NotificationEventType::ExpertOpportunityApplication,
            NotificationEventType::ExpertOpportunitySelection => $this->existingWorkflowIsStale($eventType, $context),
            NotificationEventType::ValidationReactivated => $this->validationStatusIsStale($context, 'active'),
            NotificationEventType::ValidationSuspended => $this->validationStatusIsStale($context, 'suspended'),
            NotificationEventType::ValidationRevoked => $this->validationStatusIsStale($context, 'revoked'),
            NotificationEventType::ValidationSuperseded => $this->validationStatusIsStale($context, 'superseded'),
            NotificationEventType::TrustStateChanged => $this->monitorStatusIsStale($context, ['active']),
            NotificationEventType::TrustMonitoringFailed => $this->monitorStatusIsStale($context, ['failed', 'invalid']),
            NotificationEventType::TrustMonitoringRecovered => $this->monitorStatusIsStale($context, ['active']),
            NotificationEventType::TrustMonitoringStale => $this->monitorStatusIsStale($context, ['stale']),
            NotificationEventType::SubscriptionCreated => $this->subscriptionStatusIsStale($context, 'pending'),
            NotificationEventType::SubscriptionActivated,
            NotificationEventType::SubscriptionRenewed,
            NotificationEventType::SubscriptionRecovered => $this->subscriptionStatusIsStale($context, 'active'),
            NotificationEventType::SubscriptionPaymentFailed => $this->subscriptionStatusIsStale($context, 'payment_failed'),
            NotificationEventType::SubscriptionRefunded => $this->subscriptionStatusIsStale($context, 'refunded'),
            NotificationEventType::SubscriptionCancelled => $this->subscriptionStatusIsStale($context, 'cancelled'),
            NotificationEventType::SubscriptionExpired => $this->subscriptionStatusIsStale($context, 'expired'),
            null => true,
        };
    }

    /** @param array<string, mixed> $context */
    private function existingWorkflowIsStale(NotificationEventType $eventType, array $context): bool
    {
        return match ($eventType) {
            NotificationEventType::ReportDelivered => $this->reportIsStale($context),
            NotificationEventType::ClarificationSubmitted => $this->clarificationIsStale($context, 'open'),
            NotificationEventType::ClarificationAnswered => $this->clarificationIsStale($context, 'answered'),
            NotificationEventType::DisputeSubmitted => $this->disputeIsStale($context),
            NotificationEventType::DisputeReviewerAssigned => $this->reviewerIsStale($context),
            NotificationEventType::DisputeResolved => $this->disputeIsStale($context, true),
            NotificationEventType::ImprovementOpportunityCreated,
            NotificationEventType::ImprovementOpportunityAssigned => $this->improvementOpportunityIsStale($context, false),
            NotificationEventType::ImprovementOpportunityCompleted => $this->improvementOpportunityIsStale($context, true),
            default => false,
        };
    }

    /** @param array<string, mixed> $context */
    private function validationStatusIsStale(array $context, string $expected): bool
    {
        $id = $context['validation_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $validation = Validation::query()->find((int) $id);

        return $validation === null || $validation->status->value !== $expected;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $expected
     */
    private function monitorStatusIsStale(array $context, array $expected): bool
    {
        $id = $context['validation_trust_monitor_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $monitor = ValidationTrustMonitor::query()->find((int) $id);
        if ($monitor === null || in_array($monitor->status->value, $expected, true) === false) {
            return true;
        }

        $eventFingerprint = $context['observed_fingerprint'] ?? $context['failure_fingerprint'] ?? null;
        if (is_string($eventFingerprint)) {
            $currentFingerprint = $monitor->observed_fingerprint ?? $monitor->failure_fingerprint;

            return $eventFingerprint !== $currentFingerprint;
        }

        return false;
    }

    /** @param array<string, mixed> $context */
    private function subscriptionStatusIsStale(array $context, string $expected): bool
    {
        $id = $context['subscription_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $subscription = Subscription::query()->find((int) $id);

        return $subscription === null || $subscription->status->value !== $expected;
    }

    /** @param array<string, mixed> $context */
    private function assignmentIsStale(array $context): bool
    {
        $id = $context['assignment_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $assignment = AuditorAssignment::query()->find((int) $id);

        return $assignment === null || $assignment->status !== 'offered';
    }

    /** @param array<string, mixed> $context */
    private function auditorEvaluationIsStale(array $context): bool
    {
        $id = $context['auditor_evaluation_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $evaluation = AuditorEvaluation::query()->find((int) $id);

        return $evaluation === null || $evaluation->status !== 'submitted';
    }

    /** @param array<string, mixed> $context */
    private function reportIsStale(array $context): bool
    {
        $id = $context['report_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $report = Report::query()->find((int) $id);

        return $report === null || $report->delivered_at === null;
    }

    /** @param array<string, mixed> $context */
    private function clarificationIsStale(array $context, string $expectedStatus): bool
    {
        $id = $context['clarification_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $request = ClarificationRequest::query()->find((int) $id);

        return $request === null || $request->status->value !== $expectedStatus;
    }

    /** @param array<string, mixed> $context */
    private function disputeIsStale(array $context, bool $resolved = false): bool
    {
        $id = $context['dispute_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $dispute = Dispute::query()->find((int) $id);
        if ($dispute === null) {
            return true;
        }

        if ($resolved) {
            return $dispute->status->value !== 'resolved';
        }

        return in_array($dispute->status->value, ['submitted', 'under_review'], true) === false;
    }

    /** @param array<string, mixed> $context */
    private function reviewerIsStale(array $context): bool
    {
        $id = $context['dispute_reviewer_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $reviewer = DisputeReviewer::query()->find((int) $id);

        return $reviewer === null || $reviewer->status !== 'assigned';
    }

    /** @param array<string, mixed> $context */
    private function improvementOpportunityIsStale(array $context, bool $completed): bool
    {
        $id = $context['improvement_opportunity_id'] ?? null;
        if (is_int($id) === false && ctype_digit((string) $id) === false) {
            return true;
        }

        $opportunity = ImprovementOpportunity::query()->find((int) $id);
        if ($opportunity === null) {
            return true;
        }

        if ($completed) {
            return $opportunity->status !== ImprovementOpportunityStatus::Completed;
        }

        return in_array($opportunity->status, [ImprovementOpportunityStatus::Open, ImprovementOpportunityStatus::InProgress], true) === false;
    }
}
