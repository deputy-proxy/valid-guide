<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\EvaluationStatus;
use App\Models\CreatorAction;
use App\Models\Evaluation;
use App\Models\Finding;
use App\Models\ImprovementGuidance;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

final class CreatorActionPlanner
{
    /** @return Collection<int, CreatorAction> */
    public function generate(Evaluation $evaluation, Organization $organization, User $createdBy): Collection
    {
        if ($evaluation->request?->organization_id !== $organization->id) {
            throw new DomainStateTransitionException('The action organization does not own the evaluation.');
        }

        if ($evaluation->status !== EvaluationStatus::Completed) {
            throw new DomainStateTransitionException('Creator actions can only be generated after an evaluation is completed.');
        }

        if (! $organization->users()->whereKey($createdBy->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists()) {
            throw new DomainStateTransitionException('The user cannot generate creator actions for this organization.');
        }

        $actionWorkflow = app(CreatorActionWorkflow::class);
        $opportunityWorkflow = app(ImprovementOpportunityWorkflow::class);
        $findings = $evaluation->findings()
            ->with('auditorEvaluation')
            ->get()
            ->filter(fn (Finding $finding): bool => $finding->auditor_evaluation_id === null || $finding->auditorEvaluation?->locked_at !== null)
            ->filter(fn (Finding $finding): bool => in_array(strtolower((string) $finding->type), ['weakness', 'recommendation', 'improvement', 'gap'], true));

        return $findings->map(function (Finding $finding) use ($evaluation, $organization, $createdBy, $actionWorkflow, $opportunityWorkflow): CreatorAction {
            $guidance = ImprovementGuidance::query()
                ->where('evaluation_id', $evaluation->id)
                ->where('finding_id', $finding->id)
                ->whereNotNull('creator_visible_at')
                ->latest('id')
                ->first();

            $opportunity = $opportunityWorkflow->createFromFinding($finding, $organization, $createdBy, $guidance);
            $existing = CreatorAction::query()
                ->where('evaluation_id', $evaluation->id)
                ->where('finding_id', $finding->id)
                ->first();

            if ($existing !== null) {
                if ($existing->improvement_opportunity_id !== $opportunity->id) {
                    $existing->improvement_opportunity_id = $opportunity->id;
                    $existing->save();
                }

                return $existing;
            }

            return $actionWorkflow->create(
                $evaluation,
                $organization,
                $createdBy,
                $this->title($finding),
                $this->description($finding),
                $this->priority($finding),
                $finding,
                $guidance,
                $opportunity,
            );
        })->values();
    }

    private function title(Finding $finding): string
    {
        return 'Address: '.trim((string) $finding->title);
    }

    private function description(Finding $finding): string
    {
        return 'Target outcome: '.trim((string) $finding->description)
            ."\n\nEvidence required: Provide evidence showing how this finding has been addressed in the product.";
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
