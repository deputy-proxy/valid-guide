<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\DisputeReviewer;
use App\Models\Report;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class WorkflowNotificationInbox
{
    /** @return Collection<int, array<string, mixed>> */
    public function for(User $user, int $limit = 50): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (DatabaseNotification $notification): array {
                $data = $notification->data;
                $eventType = NotificationEventType::tryFrom((string) ($data['event_type'] ?? ''));

                return [
                    'id' => $notification->getKey(),
                    'category' => (string) ($data['category'] ?? 'system'),
                    'event_type' => $eventType?->value,
                    'title' => (string) ($data['title'] ?? 'Notification'),
                    'body' => (string) ($data['body'] ?? ''),
                    'read' => $notification->read(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'stale' => $this->isStale($eventType, $data['context'] ?? []),
                ];
            });
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

    /** @param mixed $context */
    private function isStale(?NotificationEventType $eventType, mixed $context): bool
    {
        if (! is_array($context)) {
            return true;
        }

        return match ($eventType) {
            NotificationEventType::AuditorAssignmentCreated => $this->assignmentIsStale($context),
            NotificationEventType::AuditorEvaluationSubmitted => $this->auditorEvaluationIsStale($context),
            NotificationEventType::EvaluationDecisionRecorded,
            NotificationEventType::ValidationIssued,
            NotificationEventType::DisputeResolved => false,
            NotificationEventType::ReportDelivered => $this->reportIsStale($context),
            NotificationEventType::ClarificationSubmitted => $this->clarificationIsStale($context, 'open'),
            NotificationEventType::ClarificationAnswered => $this->clarificationIsStale($context, 'answered'),
            NotificationEventType::DisputeSubmitted => $this->disputeIsStale($context),
            NotificationEventType::DisputeReviewerAssigned => $this->reviewerIsStale($context),
            null => true,
        };
    }

    /** @param array<string, mixed> $context */
    private function assignmentIsStale(array $context): bool
    {
        $id = $context['assignment_id'] ?? null;
        return ! is_int($id) && ! ctype_digit((string) $id)
            ? true
            : ($assignment = AuditorAssignment::query()->find((int) $id)) === null || $assignment->status !== 'offered';
    }

    /** @param array<string, mixed> $context */
    private function auditorEvaluationIsStale(array $context): bool
    {
        $id = $context['auditor_evaluation_id'] ?? null;
        return ! is_int($id) && ! ctype_digit((string) $id)
            ? true
            : ($evaluation = AuditorEvaluation::query()->find((int) $id)) === null || $evaluation->status !== 'submitted';
    }

    /** @param array<string, mixed> $context */
    private function reportIsStale(array $context): bool
    {
        $id = $context['report_id'] ?? null;
        return ! is_int($id) && ! ctype_digit((string) $id)
            ? true
            : ($report = Report::query()->find((int) $id)) === null || $report->delivered_at === null;
    }

    /** @param array<string, mixed> $context */
    private function clarificationIsStale(array $context, string $expectedStatus): bool
    {
        $id = $context['clarification_id'] ?? null;
        return ! is_int($id) && ! ctype_digit((string) $id)
            ? true
            : ($request = ClarificationRequest::query()->find((int) $id)) === null || $request->status->value !== $expectedStatus;
    }

    /** @param array<string, mixed> $context */
    private function disputeIsStale(array $context, bool $resolved = false): bool
    {
        $id = $context['dispute_id'] ?? null;
        if (! is_int($id) && ! ctype_digit((string) $id)) {
            return true;
        }

        $dispute = Dispute::query()->find((int) $id);
        if ($dispute === null) {
            return true;
        }

        return $resolved
            ? $dispute->status->value !== 'resolved'
            : ! in_array($dispute->status->value, ['submitted', 'under_review'], true);
    }

    /** @param array<string, mixed> $context */
    private function reviewerIsStale(array $context): bool
    {
        $id = $context['dispute_reviewer_id'] ?? null;
        return ! is_int($id) && ! ctype_digit((string) $id)
            ? true
            : ($reviewer = DisputeReviewer::query()->find((int) $id)) === null || $reviewer->status !== 'assigned';
    }
}
