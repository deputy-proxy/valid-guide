<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementOpportunityStatus;
use App\Models\Evaluation;
use App\Models\Finding;
use App\Models\ImprovementGuidance;
use App\Models\ImprovementOpportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ImprovementOpportunityWorkflow
{
    public function createFromFinding(
        Finding $finding,
        Organization $organization,
        User $createdBy,
        ?ImprovementGuidance $guidance = null,
    ): ImprovementOpportunity {
        $evaluation = $finding->evaluation()->with('request')->firstOrFail();
        $this->authorize($evaluation, $organization, $createdBy);

        if ($finding->evaluation_id !== $evaluation->id) {
            throw new DomainStateTransitionException('The finding does not belong to the evaluation.');
        }

        if ($guidance !== null && (
            $guidance->evaluation_id !== $evaluation->id
            || $guidance->organization_id !== $organization->id
        )) {
            throw new DomainStateTransitionException('The improvement guidance does not belong to the evaluation organization.');
        }

        return DB::transaction(function () use ($finding, $evaluation, $organization, $createdBy, $guidance): ImprovementOpportunity {
            $existing = ImprovementOpportunity::query()
                ->where('evaluation_id', $evaluation->id)
                ->where('finding_id', $finding->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $opportunity = ImprovementOpportunity::query()->create([
                'organization_id' => $organization->id,
                'evaluation_id' => $evaluation->id,
                'product_release_id' => $evaluation->product_release_id,
                'finding_id' => $finding->id,
                'improvement_guidance_id' => $guidance?->id,
                'created_by' => $createdBy->id,
                'title' => 'Improve: '.trim((string) $finding->title),
                'target_outcome' => trim((string) $finding->description),
                'evidence_required' => 'Provide evidence showing how this finding has been addressed in the product.',
                'priority' => $this->priority($finding),
                'status' => ImprovementOpportunityStatus::Open,
            ]);

            AuditLogger::record(
                event: 'improvement_opportunity.created',
                auditable: $opportunity,
                after: [
                    'evaluation_id' => $evaluation->id,
                    'finding_id' => $finding->id,
                    'improvement_guidance_id' => $guidance?->id,
                    'priority' => $opportunity->priority->value,
                ],
                actor: $createdBy,
            );

            app(WorkflowNotificationService::class)->improvementOpportunityCreated($opportunity);

            return $opportunity->refresh();
        });
    }

    public function transition(
        ImprovementOpportunity $opportunity,
        User $actor,
        ImprovementOpportunityStatus $status,
    ): ImprovementOpportunity {
        $this->authorizeOpportunity($opportunity, $actor);

        return DB::transaction(function () use ($opportunity, $actor, $status): ImprovementOpportunity {
            $opportunity = ImprovementOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            $from = $opportunity->status;

            if (! $this->canTransition($from, $status)) {
                throw new DomainStateTransitionException(sprintf(
                    'Improvement opportunity cannot transition from %s to %s.',
                    $from->value,
                    $status->value,
                ));
            }

            if ($status === ImprovementOpportunityStatus::Completed) {
                throw new DomainStateTransitionException('Completion requires evidence.');
            }

            $opportunity->status = $status;
            $opportunity->dismissed_at = $status === ImprovementOpportunityStatus::Dismissed ? now() : null;
            $opportunity->save();

            AuditLogger::record(
                event: 'improvement_opportunity.status_changed',
                auditable: $opportunity,
                before: ['status' => $from->value],
                after: ['status' => $status->value],
                actor: $actor,
            );

            return $opportunity->refresh();
        });
    }

    public function complete(
        ImprovementOpportunity $opportunity,
        User $actor,
        string $completionEvidence,
    ): ImprovementOpportunity {
        $this->authorizeOpportunity($opportunity, $actor);

        if (trim($completionEvidence) === '') {
            throw new DomainStateTransitionException('Completion evidence is required.');
        }

        return DB::transaction(function () use ($opportunity, $actor, $completionEvidence): ImprovementOpportunity {
            $opportunity = ImprovementOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();

            if (! in_array($opportunity->status, [ImprovementOpportunityStatus::Open, ImprovementOpportunityStatus::InProgress], true)) {
                throw new DomainStateTransitionException('Only open or in-progress opportunities can be completed.');
            }

            $opportunity->status = ImprovementOpportunityStatus::Completed;
            $opportunity->completion_evidence = trim($completionEvidence);
            $opportunity->completed_at = now();
            $opportunity->save();

            AuditLogger::record(
                event: 'improvement_opportunity.completed',
                auditable: $opportunity,
                after: ['status' => $opportunity->status->value],
                actor: $actor,
            );

            app(WorkflowNotificationService::class)->improvementOpportunityCompleted($opportunity);

            return $opportunity->refresh();
        });
    }

    public function assign(
        ImprovementOpportunity $opportunity,
        User $actor,
        ?User $assignee,
    ): ImprovementOpportunity {
        $this->authorizeOpportunity($opportunity, $actor);

        if ($assignee !== null && ! $opportunity->organization->users()->whereKey($assignee->id)->exists()) {
            throw new DomainStateTransitionException('The assignee must belong to the opportunity organization.');
        }

        return DB::transaction(function () use ($opportunity, $actor, $assignee): ImprovementOpportunity {
            $opportunity = ImprovementOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            $before = $opportunity->assigned_to;
            $opportunity->assigned_to = $assignee?->id;
            $opportunity->save();

            AuditLogger::record(
                event: 'improvement_opportunity.assigned',
                auditable: $opportunity,
                before: ['assigned_to' => $before],
                after: ['assigned_to' => $assignee?->id],
                actor: $actor,
            );

            if ($assignee !== null) {
                app(WorkflowNotificationService::class)->improvementOpportunityAssigned($opportunity, $assignee);
            }

            return $opportunity->refresh();
        });
    }

    public function supersede(
        ImprovementOpportunity $opportunity,
        ImprovementOpportunity $replacement,
        User $actor,
    ): ImprovementOpportunity {
        $this->authorizeOpportunity($opportunity, $actor);
        $this->authorizeOpportunity($replacement, $actor);

        if ($opportunity->evaluation_id === $replacement->evaluation_id) {
            throw new DomainStateTransitionException('An opportunity can only be superseded by a later evaluation.');
        }

        return DB::transaction(function () use ($opportunity, $replacement, $actor): ImprovementOpportunity {
            $opportunity = ImprovementOpportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();

            if (in_array($opportunity->status, [ImprovementOpportunityStatus::Completed, ImprovementOpportunityStatus::Dismissed, ImprovementOpportunityStatus::Superseded], true)) {
                throw new DomainStateTransitionException('Only active opportunities can be superseded.');
            }

            $opportunity->status = ImprovementOpportunityStatus::Superseded;
            $opportunity->superseded_by_id = $replacement->id;
            $opportunity->superseded_at = now();
            $opportunity->save();

            AuditLogger::record(
                event: 'improvement_opportunity.superseded',
                auditable: $opportunity,
                after: ['superseded_by_id' => $replacement->id],
                actor: $actor,
            );

            return $opportunity->refresh();
        });
    }

    private function authorize(Evaluation $evaluation, Organization $organization, User $user): void
    {
        if ($evaluation->request?->organization_id !== $organization->id) {
            throw new DomainStateTransitionException('The opportunity organization does not own the evaluation.');
        }

        if ($evaluation->status->value !== 'completed') {
            throw new DomainStateTransitionException('Improvement opportunities can only be managed after an evaluation is completed.');
        }

        if (! $organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            throw new DomainStateTransitionException('The user cannot manage improvement opportunities for this organization.');
        }
    }

    private function authorizeOpportunity(ImprovementOpportunity $opportunity, User $user): void
    {
        if ($opportunity->organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            return;
        }

        throw new DomainStateTransitionException('The user cannot manage this improvement opportunity.');
    }

    private function canTransition(ImprovementOpportunityStatus $from, ImprovementOpportunityStatus $to): bool
    {
        return match ($from) {
            ImprovementOpportunityStatus::Open => in_array($to, [ImprovementOpportunityStatus::InProgress, ImprovementOpportunityStatus::Dismissed], true),
            ImprovementOpportunityStatus::InProgress => in_array($to, [ImprovementOpportunityStatus::Open, ImprovementOpportunityStatus::Dismissed], true),
            ImprovementOpportunityStatus::Completed,
            ImprovementOpportunityStatus::Dismissed,
            ImprovementOpportunityStatus::Superseded => false,
        };
    }

    private function priority(Finding $finding): CreatorActionPriority
    {
        return match (strtolower((string) $finding->severity)) {
            'critical' => CreatorActionPriority::Critical,
            'high' => CreatorActionPriority::High,
            'low' => CreatorActionPriority::Low,
            default => CreatorActionPriority::Medium,
        };
    }
}
