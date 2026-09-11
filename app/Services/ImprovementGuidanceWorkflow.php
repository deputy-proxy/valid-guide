<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\EvaluationStatus;
use App\Enums\ImprovementGuidanceCategory;
use App\Enums\ImprovementGuidanceStatus;
use App\Models\Evaluation;
use App\Models\Finding;
use App\Models\ImprovementGuidance;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ImprovementGuidanceWorkflow
{
    /** @param array<string, mixed>|null $applicability */
    public function create(
        Evaluation $evaluation,
        Organization $organization,
        User $createdBy,
        string $title,
        string $guidance,
        string $rationale,
        ImprovementGuidanceCategory $category,
        CreatorActionPriority $priority = CreatorActionPriority::Medium,
        ?Finding $finding = null,
        ?array $applicability = null,
        bool $requiresAction = false,
    ): ImprovementGuidance {
        $this->authorize($evaluation, $organization, $createdBy);

        if (trim($title) === '' || trim($guidance) === '' || trim($rationale) === '') {
            throw new DomainStateTransitionException('Improvement guidance requires a title, guidance and rationale.');
        }

        if ($finding !== null && $finding->evaluation_id !== $evaluation->id) {
            throw new DomainStateTransitionException('The guidance finding does not belong to the evaluation.');
        }

        return DB::transaction(function () use ($evaluation, $organization, $createdBy, $title, $guidance, $rationale, $category, $priority, $finding, $applicability, $requiresAction): ImprovementGuidance {
            $record = ImprovementGuidance::query()->create([
                'organization_id' => $organization->id,
                'evaluation_id' => $evaluation->id,
                'report_id' => $evaluation->report?->id,
                'report_version_id' => $evaluation->report?->current_version_id,
                'finding_id' => $finding?->id,
                'created_by' => $createdBy->id,
                'category' => $category,
                'title' => trim($title),
                'guidance' => trim($guidance),
                'rationale' => trim($rationale),
                'priority' => $priority,
                'applicability' => $applicability,
                'requires_action' => $requiresAction,
                'status' => ImprovementGuidanceStatus::Draft,
            ]);

            AuditLogger::record(
                event: 'improvement_guidance.created',
                auditable: $record,
                after: [
                    'evaluation_id' => $evaluation->id,
                    'finding_id' => $finding?->id,
                    'category' => $category->value,
                    'priority' => $priority->value,
                    'requires_action' => $requiresAction,
                ],
                actor: $createdBy,
            );

            return $record->refresh();
        });
    }

    public function publish(ImprovementGuidance $guidance, User $actor): ImprovementGuidance
    {
        $this->authorizeGuidance($guidance, $actor);

        if ($guidance->status !== ImprovementGuidanceStatus::Draft) {
            throw new DomainStateTransitionException('Only draft improvement guidance can be published.');
        }

        return DB::transaction(function () use ($guidance, $actor): ImprovementGuidance {
            $guidance = ImprovementGuidance::query()->whereKey($guidance->id)->lockForUpdate()->firstOrFail();
            $guidance->status = ImprovementGuidanceStatus::Published;
            $guidance->creator_visible_at = now();
            $guidance->save();

            AuditLogger::record(
                event: 'improvement_guidance.published',
                auditable: $guidance,
                after: ['status' => ImprovementGuidanceStatus::Published->value],
                actor: $actor,
            );

            return $guidance->refresh();
        });
    }

    public function complete(ImprovementGuidance $guidance, User $actor): ImprovementGuidance
    {
        $this->authorizeGuidance($guidance, $actor);

        if ($guidance->status !== ImprovementGuidanceStatus::Published) {
            throw new DomainStateTransitionException('Only published improvement guidance can be completed.');
        }

        return DB::transaction(function () use ($guidance, $actor): ImprovementGuidance {
            $guidance = ImprovementGuidance::query()->whereKey($guidance->id)->lockForUpdate()->firstOrFail();
            $guidance->status = ImprovementGuidanceStatus::Completed;
            $guidance->save();

            AuditLogger::record(
                event: 'improvement_guidance.completed',
                auditable: $guidance,
                after: ['status' => ImprovementGuidanceStatus::Completed->value],
                actor: $actor,
            );

            return $guidance->refresh();
        });
    }

    public function dismiss(ImprovementGuidance $guidance, User $actor): ImprovementGuidance
    {
        $this->authorizeGuidance($guidance, $actor);

        if (! in_array($guidance->status, [ImprovementGuidanceStatus::Draft, ImprovementGuidanceStatus::Published], true)) {
            throw new DomainStateTransitionException('Only draft or published improvement guidance can be dismissed.');
        }

        return DB::transaction(function () use ($guidance, $actor): ImprovementGuidance {
            $guidance = ImprovementGuidance::query()->whereKey($guidance->id)->lockForUpdate()->firstOrFail();
            $guidance->status = ImprovementGuidanceStatus::Dismissed;
            $guidance->save();

            AuditLogger::record(
                event: 'improvement_guidance.dismissed',
                auditable: $guidance,
                after: ['status' => ImprovementGuidanceStatus::Dismissed->value],
                actor: $actor,
            );

            return $guidance->refresh();
        });
    }

    private function authorize(Evaluation $evaluation, Organization $organization, User $user): void
    {
        if ($evaluation->request?->organization_id !== $organization->id) {
            throw new DomainStateTransitionException('The guidance organization does not own the evaluation.');
        }

        if ($evaluation->status !== EvaluationStatus::Completed) {
            throw new DomainStateTransitionException('Improvement guidance can only be managed after an evaluation is completed.');
        }

        if (! $organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            throw new DomainStateTransitionException('The user cannot manage improvement guidance for this organization.');
        }
    }

    private function authorizeGuidance(ImprovementGuidance $guidance, User $user): void
    {
        if ($guidance->organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            return;
        }

        throw new DomainStateTransitionException('The user cannot manage this improvement guidance.');
    }
}
