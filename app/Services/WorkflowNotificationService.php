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
use App\Models\ImprovementOpportunity;
use App\Models\Organization;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Validation;
use App\Models\ValidationTrustMonitor;
use App\Notifications\WorkflowNotification;
use Illuminate\Notifications\DatabaseNotification;

final class WorkflowNotificationService
{
    public function auditorAssignmentCreated(AuditorAssignment $assignment): void
    {
        $assignment->loadMissing('evaluation.product', 'auditor');
        $productTitle = $assignment->evaluation->product->title;
        $this->send($assignment->auditor, NotificationCategory::Assignment, NotificationEventType::AuditorAssignmentCreated, 'New evaluation assignment', sprintf('You have been assigned to evaluate %s.', $productTitle), ['assignment_id' => $assignment->getKey(), 'evaluation_id' => $assignment->evaluation_id]);
    }

    public function auditorEvaluationSubmitted(AuditorEvaluation $auditorEvaluation): void
    {
        $this->sendToPlatformAdmins(NotificationCategory::Submission, NotificationEventType::AuditorEvaluationSubmitted, 'Auditor evaluation submitted', 'An Auditor has submitted an evaluation requiring platform review.', ['auditor_evaluation_id' => $auditorEvaluation->getKey(), 'evaluation_id' => $auditorEvaluation->evaluation_id]);
    }

    public function evaluationDecisionRecorded(EvaluationDecision $decision): void
    {
        $decision->loadMissing('evaluation.request.organization');
        $organization = $decision->evaluation?->request?->organization;
        if ($organization === null) {
            return;
        }

        $this->sendToOrganization($organization, NotificationCategory::Decision, NotificationEventType::EvaluationDecisionRecorded, 'Evaluation decision recorded', 'An authorized decision has been recorded for your evaluation.', ['evaluation_decision_id' => $decision->getKey(), 'evaluation_id' => $decision->evaluation_id]);
    }

    public function validationIssued(Validation $validation): void
    {
        $validation->loadMissing('evaluation.request.organization');
        $organization = $validation->evaluation?->request?->organization;
        if ($organization === null) {
            return;
        }

        $this->sendToOrganization($organization, NotificationCategory::Decision, NotificationEventType::ValidationIssued, 'Validation issued', 'Your product has received a Valid.guide validation.', ['validation_id' => $validation->getKey(), 'evaluation_id' => $validation->evaluation_id, 'verification_identifier' => $validation->verification_identifier]);
    }

    public function validationStatusChanged(Validation $validation): void
    {
        $validation->loadMissing('productRelease.product.organization');
        $organization = $validation->productRelease?->product?->organization;
        if ($organization === null) {
            return;
        }

        $definition = match ($validation->status->value) {
            'suspended' => [NotificationEventType::ValidationSuspended, 'Validation suspended', 'The validation for your product has been suspended.'],
            'revoked' => [NotificationEventType::ValidationRevoked, 'Validation revoked', 'The validation for your product has been revoked.'],
            'superseded' => [NotificationEventType::ValidationSuperseded, 'Validation superseded', 'The validation for your product has been superseded by a newer validation.'],
            default => null,
        };

        if ($definition === null) {
            return;
        }

        [$eventType, $title, $body] = $definition;
        $this->sendToOrganization($organization, NotificationCategory::Trust, $eventType, $title, $body, ['validation_id' => $validation->getKey(), 'evaluation_id' => $validation->evaluation_id, 'status' => $validation->status->value, 'updated_at' => $validation->updated_at?->toIso8601String()]);
    }

    public function reportDelivered(Report $report): void
    {
        $report->loadMissing('evaluation.request.organization', 'evaluation.product');
        $organization = $report->evaluation?->request?->organization;
        if ($organization === null) {
            return;
        }

        $productTitle = $report->evaluation->product->title;
        $this->sendToOrganization($organization, NotificationCategory::Report, NotificationEventType::ReportDelivered, 'Evaluation report available', sprintf('The evaluation report for %s is now available.', $productTitle), ['report_id' => $report->getKey(), 'evaluation_id' => $report->evaluation_id]);
    }

    public function clarificationSubmitted(ClarificationRequest $request): void
    {
        $this->sendToPlatformAdmins(NotificationCategory::Clarification, NotificationEventType::ClarificationSubmitted, 'Clarification request submitted', 'A creator has submitted a clarification request requiring review.', ['clarification_id' => $request->getKey(), 'evaluation_id' => $request->evaluation_id]);
    }

    public function clarificationAnswered(ClarificationRequest $request): void
    {
        $this->sendToOrganization(Organization::query()->findOrFail($request->organization_id), NotificationCategory::Clarification, NotificationEventType::ClarificationAnswered, 'Clarification answered', 'Your clarification request has received an answer.', ['clarification_id' => $request->getKey(), 'evaluation_id' => $request->evaluation_id]);
    }

    public function disputeSubmitted(Dispute $dispute): void
    {
        $this->sendToPlatformAdmins(NotificationCategory::Dispute, NotificationEventType::DisputeSubmitted, 'Formal dispute submitted', 'A creator has submitted a formal dispute requiring review.', ['dispute_id' => $dispute->getKey(), 'evaluation_id' => $dispute->evaluation_id]);
    }

    public function disputeReviewerAssigned(Dispute $dispute, User $reviewer, int $reviewerAssignmentId): void
    {
        $this->send($reviewer, NotificationCategory::Dispute, NotificationEventType::DisputeReviewerAssigned, 'Dispute review assigned', 'You have been assigned to an independent dispute review.', ['dispute_id' => $dispute->getKey(), 'evaluation_id' => $dispute->evaluation_id, 'dispute_reviewer_id' => $reviewerAssignmentId]);
    }

    public function disputeResolved(Dispute $dispute): void
    {
        $this->sendToOrganization(Organization::query()->findOrFail($dispute->organization_id), NotificationCategory::Dispute, NotificationEventType::DisputeResolved, 'Formal dispute resolved', 'Your formal dispute has been resolved.', ['dispute_id' => $dispute->getKey(), 'evaluation_id' => $dispute->evaluation_id]);
    }

    public function improvementOpportunityCreated(ImprovementOpportunity $opportunity): void
    {
        $opportunity->loadMissing('organization');
        $this->sendToOrganization($opportunity->organization, NotificationCategory::System, NotificationEventType::ImprovementOpportunityCreated, 'Improvement opportunity available', sprintf('A new improvement opportunity is available: %s.', $opportunity->title), ['improvement_opportunity_id' => $opportunity->getKey(), 'evaluation_id' => $opportunity->evaluation_id]);
    }

    public function improvementOpportunityAssigned(ImprovementOpportunity $opportunity, User $assignee): void
    {
        $this->send($assignee, NotificationCategory::Assignment, NotificationEventType::ImprovementOpportunityAssigned, 'Improvement opportunity assigned', sprintf('You have been assigned an improvement opportunity: %s.', $opportunity->title), ['improvement_opportunity_id' => $opportunity->getKey(), 'evaluation_id' => $opportunity->evaluation_id]);
    }

    public function improvementOpportunityCompleted(ImprovementOpportunity $opportunity): void
    {
        $opportunity->loadMissing('organization');
        $this->sendToOrganization($opportunity->organization, NotificationCategory::System, NotificationEventType::ImprovementOpportunityCompleted, 'Improvement opportunity completed', sprintf('The improvement opportunity "%s" has been completed.', $opportunity->title), ['improvement_opportunity_id' => $opportunity->getKey(), 'evaluation_id' => $opportunity->evaluation_id]);
    }

    public function trustStateChanged(ValidationTrustMonitor $monitor): void
    {
        $this->sendForMonitor($monitor, NotificationEventType::TrustStateChanged, 'Validation trust state changed', 'The authoritative trust state of a monitored validation has changed.');
    }

    public function trustMonitoringFailed(ValidationTrustMonitor $monitor): void
    {
        $this->sendForMonitor($monitor, NotificationEventType::TrustMonitoringFailed, 'Validation trust monitoring failed', 'A monitored validation could not be verified and requires attention.');
    }

    public function trustMonitoringRecovered(ValidationTrustMonitor $monitor): void
    {
        $this->sendForMonitor($monitor, NotificationEventType::TrustMonitoringRecovered, 'Validation trust monitoring recovered', 'Validation trust monitoring has recovered and is reporting an active authoritative state.');
    }

    public function trustMonitoringStale(ValidationTrustMonitor $monitor): void
    {
        $this->sendForMonitor($monitor, NotificationEventType::TrustMonitoringStale, 'Validation trust monitoring is stale', 'A monitored validation has not been checked within its expected monitoring window.');
    }

    public function subscriptionLifecycleChanged(Subscription $subscription, NotificationEventType $eventType): void
    {
        $subscription->loadMissing('organization');
        $definition = match ($eventType) {
            NotificationEventType::SubscriptionCreated => ['Subscription created', 'A subscription has been created and is awaiting activation.'],
            NotificationEventType::SubscriptionActivated => ['Subscription activated', 'Your subscription is now active.'],
            NotificationEventType::SubscriptionRenewed => ['Subscription renewed', 'Your subscription has been renewed for the next billing period.'],
            NotificationEventType::SubscriptionPaymentFailed => ['Subscription payment failed', 'A subscription payment failed and requires attention.'],
            NotificationEventType::SubscriptionRecovered => ['Subscription payment recovered', 'Your subscription has recovered from a payment failure.'],
            NotificationEventType::SubscriptionRefunded => ['Subscription refunded', 'Your subscription payment has been refunded.'],
            NotificationEventType::SubscriptionCancelled => ['Subscription cancelled', 'Your subscription has been cancelled.'],
            NotificationEventType::SubscriptionExpired => ['Subscription expired', 'Your subscription has expired.'],
            default => null,
        };

        if ($definition === null || $subscription->organization === null) {
            return;
        }

        [$title, $body] = $definition;
        $this->sendToOrganization($subscription->organization, NotificationCategory::Billing, $eventType, $title, $body, ['subscription_id' => $subscription->getKey(), 'organization_id' => $subscription->organization_id, 'status' => $subscription->status->value, 'updated_at' => $subscription->updated_at?->toIso8601String()]);
    }

    /** @param array<string, int|string|null> $context */
    private function send(User $user, NotificationCategory $category, NotificationEventType $eventType, string $title, string $body, array $context): void
    {
        if (! $user->canReceiveNotification($category)) {
            return;
        }

        $dedupeKey = hash('sha256', $eventType->value.'|'.json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $exists = DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->getKey())
            ->whereJsonContains('data->dedupe_key', $dedupeKey)
            ->exists();

        if ($exists) {
            return;
        }

        $context['dedupe_key'] = $dedupeKey;
        $actionUrl = $this->actionUrl($eventType, $context);
        $user->notify(new WorkflowNotification($category, $eventType, $title, $body, $context, $actionUrl));
    }

    /** @param array<string, int|string|null> $context */
    private function actionUrl(NotificationEventType $eventType, array $context): string
    {
        $evaluationId = $context['evaluation_id'] ?? null;

        if (is_int($evaluationId) || ctype_digit((string) $evaluationId)) {
            if (in_array($eventType, [
                NotificationEventType::ValidationIssued,
                NotificationEventType::ValidationSuspended,
                NotificationEventType::ValidationRevoked,
                NotificationEventType::ValidationSuperseded,
                NotificationEventType::TrustStateChanged,
                NotificationEventType::TrustMonitoringFailed,
                NotificationEventType::TrustMonitoringRecovered,
                NotificationEventType::TrustMonitoringStale,
                NotificationEventType::ReportDelivered,
            ], true)) {
                return route('creator.reports.show', ['evaluationId' => (int) $evaluationId]);
            }
        }

        return route('dashboard');
    }

    /** @param array<string, int|string|null> $context */
    private function sendToPlatformAdmins(NotificationCategory $category, NotificationEventType $eventType, string $title, string $body, array $context): void
    {
        foreach (User::query()->whereNotNull('platform_role')->cursor() as $user) {
            $this->send($user, $category, $eventType, $title, $body, $context);
        }
    }

    /** @param array<string, int|string|null> $context */
    private function sendToOrganization(Organization $organization, NotificationCategory $category, NotificationEventType $eventType, string $title, string $body, array $context): void
    {
        foreach ($organization->users()->wherePivotIn('role', ['owner', 'admin', 'editor'])->cursor() as $user) {
            $this->send($user, $category, $eventType, $title, $body, $context);
        }
    }

    private function sendForMonitor(ValidationTrustMonitor $monitor, NotificationEventType $eventType, string $title, string $body): void
    {
        $organization = Organization::query()->find($monitor->organization_id);
        if ($organization === null) {
            return;
        }

        $this->sendToOrganization($organization, NotificationCategory::Trust, $eventType, $title, $body, [
            'validation_trust_monitor_id' => $monitor->getKey(),
            'validation_id' => $monitor->validation_id,
            'organization_id' => $monitor->organization_id,
            'status' => $monitor->status->value,
            'observed_fingerprint' => $monitor->observed_fingerprint,
            'failure_fingerprint' => $monitor->failure_fingerprint,
            'last_checked_at' => $monitor->last_checked_at,
        ]);
    }
}
