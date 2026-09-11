<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\CreatorActionStatus;
use App\Enums\EvaluationStatus;
use App\Enums\ImprovementOpportunityStatus;
use App\Models\CreatorAction;
use App\Models\Evaluation;
use App\Models\Finding;
use App\Models\ImprovementGuidance;
use App\Models\ImprovementOpportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreatorActionWorkflow
{
    public function create(
        Evaluation $evaluation,
        Organization $organization,
        User $createdBy,
        string $title,
        string $description,
        CreatorActionPriority $priority = CreatorActionPriority::Medium,
        ?Finding $finding = null,
        ?ImprovementGuidance $improvementGuidance = null,
        ?ImprovementOpportunity $improvementOpportunity = null,
    ): CreatorAction {
        $this->authorize($evaluation, $organization, $createdBy);

        if (trim($title) === '' || trim($description) === '') {
            throw new DomainStateTransitionException('A creator action requires a title and description.');
        }

        if ($finding !== null && $finding->evaluation_id !== $evaluation->id) {
            throw new DomainStateTransitionException('The action finding does not belong to the evaluation.');
        }

        if ($improvementGuidance !== null && (
            $improvementGuidance->evaluation_id !== $evaluation->id
            || $improvementGuidance->organization_id !== $organization->id
        )) {
            throw new DomainStateTransitionException('The action guidance does not belong to the evaluation organization.');
        }

        if ($improvementOpportunity !== null && (
            $improvementOpportunity->evaluation_id !== $evaluation->id
            || $improvementOpportunity->organization_id !== $organization->id
        )) {
            throw new DomainStateTransitionException('The action opportunity does not belong to the evaluation organization.');
        }

        return DB::transaction(function () use ($evaluation, $organization, $createdBy, $title, $description, $priority, $finding, $improvementGuidance, $improvementOpportunity): CreatorAction {
            $action = CreatorAction::query()->create([
                'organization_id' => $organization->id,
                'evaluation_id' => $evaluation->id,
                'finding_id' => $finding?->id,
                'improvement_guidance_id' => $improvementGuidance?->id,
                'improvement_opportunity_id' => $improvementOpportunity?->id,
                'created_by' => $createdBy->id,
                'title' => trim($title),
                'description' => trim($description),
                'priority' => $priority,
                'status' => CreatorActionStatus::Pending,
            ]);

            AuditLogger::record(
                event: 'creator_action.created',
                auditable: $action,
                after: [
                    'evaluation_id' => $evaluation->id,
                    'finding_id' => $finding?->id,
                    'improvement_guidance_id' => $improvementGuidance?->id,
                    'improvement_opportunity_id' => $improvementOpportunity?->id,
                    'priority' => $priority->value,
                ],
                actor: $createdBy,
            );

            return $action->refresh();
        });
    }

    public function transition(CreatorAction $action, User $actor, CreatorActionStatus $status): CreatorAction
    {
        $this->authorizeAction($action, $actor);

        return DB::transaction(function () use ($action, $actor, $status): CreatorAction {
            $action = CreatorAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();
            $from = $action->status;
            $opportunity = $action->improvementOpportunity;

            if (! $this->canTransition($from, $status)) {
                throw new DomainStateTransitionException(sprintf(
                    'Creator action cannot transition from %s to %s.',
                    $from->value,
                    $status->value,
                ));
            }

            if ($opportunity !== null && in_array($opportunity->status, [
                ImprovementOpportunityStatus::Completed,
                ImprovementOpportunityStatus::Dismissed,
                ImprovementOpportunityStatus::Superseded,
            ], true)) {
                throw new DomainStateTransitionException('The creator action is locked because its improvement opportunity is closed.');
            }

            $action->status = $status;
            $action->completed_at = $status === CreatorActionStatus::Completed ? now() : null;
            $action->save();

            if ($opportunity !== null) {
                $opportunityWorkflow = app(ImprovementOpportunityWorkflow::class);

                if ($status === CreatorActionStatus::InProgress && $opportunity->status === ImprovementOpportunityStatus::Open) {
                    $opportunityWorkflow->transition($opportunity, $actor, ImprovementOpportunityStatus::InProgress);
                } elseif ($status === CreatorActionStatus::Pending && $opportunity->status === ImprovementOpportunityStatus::InProgress) {
                    $opportunityWorkflow->transition($opportunity, $actor, ImprovementOpportunityStatus::Open);
                } elseif ($status === CreatorActionStatus::Cancelled) {
                    $opportunityWorkflow->transition($opportunity, $actor, ImprovementOpportunityStatus::Dismissed);
                } elseif ($status === CreatorActionStatus::Completed) {
                    $opportunityWorkflow->complete(
                        $opportunity,
                        $actor,
                        sprintf('Creator action completed: %s', $action->title),
                    );
                }
            }

            AuditLogger::record(
                event: 'creator_action.status_changed',
                auditable: $action,
                before: ['status' => $from->value],
                after: ['status' => $status->value],
                actor: $actor,
            );

            return $action->refresh();
        });
    }

    public function assign(CreatorAction $action, User $actor, ?User $assignee): CreatorAction
    {
        $this->authorizeAction($action, $actor);

        if ($assignee !== null && ! $action->organization->users()->whereKey($assignee->id)->exists()) {
            throw new DomainStateTransitionException('The assignee must belong to the action organization.');
        }

        return DB::transaction(function () use ($action, $actor, $assignee): CreatorAction {
            $action = CreatorAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();
            $before = $action->assigned_to;
            $action->assigned_to = $assignee?->id;
            $action->save();

            if ($action->improvementOpportunity !== null) {
                app(ImprovementOpportunityWorkflow::class)->assign($action->improvementOpportunity, $actor, $assignee);
            }

            AuditLogger::record(
                event: 'creator_action.assigned',
                auditable: $action,
                before: ['assigned_to' => $before],
                after: ['assigned_to' => $assignee?->id],
                actor: $actor,
            );

            return $action->refresh();
        });
    }

    private function authorize(Evaluation $evaluation, Organization $organization, User $user): void
    {
        if ($evaluation->request?->organization_id !== $organization->id) {
            throw new DomainStateTransitionException('The action organization does not own the evaluation.');
        }

        if ($evaluation->status !== EvaluationStatus::Completed) {
            throw new DomainStateTransitionException('Creator actions can only be managed after an evaluation is completed.');
        }

        if (! $organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            throw new DomainStateTransitionException('The user cannot manage creator actions for this organization.');
        }
    }

    private function authorizeAction(CreatorAction $action, User $user): void
    {
        if ($action->organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            return;
        }

        throw new DomainStateTransitionException('The user cannot manage this creator action.');
    }

    private function canTransition(CreatorActionStatus $from, CreatorActionStatus $to): bool
    {
        return match ($from) {
            CreatorActionStatus::Pending => in_array($to, [CreatorActionStatus::InProgress, CreatorActionStatus::Cancelled], true),
            CreatorActionStatus::InProgress => in_array($to, [CreatorActionStatus::Pending, CreatorActionStatus::Completed, CreatorActionStatus::Cancelled], true),
            CreatorActionStatus::Completed, CreatorActionStatus::Cancelled => false,
        };
    }
}
