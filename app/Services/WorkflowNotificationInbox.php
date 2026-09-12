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
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class WorkflowNotificationInbox
{
    /** @return Collection<int, array<string, mixed>> */
    public function for(User $user, int $limit = 50): Collection
    {
        /** @var Collection<int, array<string, mixed>> $notifications */
        $notifications = $user->notifications()->latest()->limit($limit)->get()->map(function (DatabaseNotification $notification): array {
            $data = $notification->data;
            $eventType = NotificationEventType::tryFrom((string) ($data['event_type'] ?? ''));
            return [
                'id' => (string) $notification->getKey(), 'category' => (string) ($data['category'] ?? 'system'),
                'event_type' => $eventType?->value, 'title' => (string) ($data['title'] ?? 'Notification'),
                'body' => (string) ($data['body'] ?? ''), 'read' => $notification->read(),
                'created_at' => $notification->created_at?->toIso8601String(), 'stale' => $this->isStale($eventType, $data['context'] ?? []),
            ];
        });
        return $notifications;
    }

    public function unreadCount(User $user): int { return $user->unreadNotifications()->count(); }

    public function markRead(User $user, string $notificationId): void { $user->notifications()->whereKey($notificationId)->firstOrFail()->markAsRead(); }

    public function markAllRead(User $user): void { $user->unreadNotifications()->update(['read_at' => now()]); }

    private function isStale(?NotificationEventType $eventType, mixed $context): bool
    {
        if (is_array($context) === false) return true;
        return match ($eventType) {
            NotificationEventType::AuditorAssignmentCreated => $this->assignmentIsStale($context),
            NotificationEventType::AuditorEvaluationSubmitted => $this->auditorEvaluationIsStale($context),
            NotificationEventType::EvaluationDecisionRecorded,
            NotificationEventType::ValidationIssued,
            NotificationEventType::DisputeResolved,
            NotificationEventType::ExpertOpportunityPublished,
            NotificationEventType::ExpertOpportunityApplication,
            NotificationEventType::ExpertOpportunitySelection => false,
            NotificationEventType::ReportDelivered => $this->reportIsStale($context),
            NotificationEventType::ClarificationSubmitted => $this->clarificationIsStale($context, 'open'),
            NotificationEventType::ClarificationAnswered => $this->clarificationIsStale($context, 'answered'),
            NotificationEventType::DisputeSubmitted => $this->disputeIsStale($context),
            NotificationEventType::DisputeReviewerAssigned => $this->reviewerIsStale($context),
            NotificationEventType::ImprovementOpportunityCreated,
            NotificationEventType::ImprovementOpportunityAssigned => $this->improvementOpportunityIsStale($context, false),
            NotificationEventType::ImprovementOpportunityCompleted => $this->improvementOpportunityIsStale($context, true),
            null => true,
        };
    }

    /** @param array<string, mixed> $context */
    private function assignmentIsStale(array $context): bool { $id = $context['assignment_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $assignment = AuditorAssignment::query()->find((int) $id); return $assignment === null || $assignment->status !== 'offered'; }
    /** @param array<string, mixed> $context */
    private function auditorEvaluationIsStale(array $context): bool { $id = $context['auditor_evaluation_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $evaluation = AuditorEvaluation::query()->find((int) $id); return $evaluation === null || $evaluation->status !== 'submitted'; }
    /** @param array<string, mixed> $context */
    private function reportIsStale(array $context): bool { $id = $context['report_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $report = Report::query()->find((int) $id); return $report === null || $report->delivered_at === null; }
    /** @param array<string, mixed> $context */
    private function clarificationIsStale(array $context, string $expectedStatus): bool { $id = $context['clarification_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $request = ClarificationRequest::query()->find((int) $id); return $request === null || $request->status->value !== $expectedStatus; }
    /** @param array<string, mixed> $context */
    private function disputeIsStale(array $context, bool $resolved = false): bool { $id = $context['dispute_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $dispute = Dispute::query()->find((int) $id); if ($dispute === null) return true; if ($resolved) return $dispute->status->value !== 'resolved'; return in_array($dispute->status->value, ['submitted', 'under_review'], true) === false; }
    /** @param array<string, mixed> $context */
    private function reviewerIsStale(array $context): bool { $id = $context['dispute_reviewer_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $reviewer = DisputeReviewer::query()->find((int) $id); return $reviewer === null || $reviewer->status !== 'assigned'; }
    /** @param array<string, mixed> $context */
    private function improvementOpportunityIsStale(array $context, bool $completed): bool { $id = $context['improvement_opportunity_id'] ?? null; if (is_int($id) === false && ctype_digit((string) $id) === false) return true; $opportunity = ImprovementOpportunity::query()->find((int) $id); if ($opportunity === null) return true; if ($completed) return $opportunity->status !== ImprovementOpportunityStatus::Completed; return ! in_array($opportunity->status, [ImprovementOpportunityStatus::Open, ImprovementOpportunityStatus::InProgress], true); }
}
