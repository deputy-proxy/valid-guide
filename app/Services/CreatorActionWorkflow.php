<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\CreatorActionStatus;
use App\Enums\EvaluationStatus;
use App\Models\CreatorAction;
use App\Models\Evaluation;
use App\Models\Finding;
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
    ): CreatorAction {
        $this->authorize($evaluation, $organization, $createdBy);

        if (trim($title) === '' || trim($description) === '') {
            throw new DomainStateTransitionException('A creator action requires a title and description.');
        }

        if ($finding !== null && $finding->evaluation_id !== $evaluation->id) {
            throw new DomainStateTransitionException('The action finding does not belong to the evaluation.');
        }

        return DB::transaction(function () use ($evaluation, $organization, $createdBy, $title, $description, $priority, $finding): CreatorAction {
            $action = CreatorAction::query()->create([
                'organization_id' => $organization->id,
                'evaluation_id' => $evaluation->id,
                'finding_id' => $finding?->id,
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

            if (! $this->canTransition($from, $status)) {
                throw new DomainStateTransitionException(sprintf(
                    'Creator action cannot transition from %s to %s.',
                    $from->value,
                    $status->value,
                ));
            }

            $action->status = $status;
            $action->completed_at = $status === CreatorActionStatus::Completed ? now() : null;
            $action->save();

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
