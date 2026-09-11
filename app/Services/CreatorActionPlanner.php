<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\EvaluationStatus;
use App\Models\CreatorAction;
use App\Models\Evaluation;
use App\Models\Finding;
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

        $workflow = app(CreatorActionWorkflow::class);
        $findings = $evaluation->findings()
            ->with('auditorEvaluation')
            ->get()
            ->filter(fn (Finding $finding): bool => $finding->auditor_evaluation_id === null || $finding->auditorEvaluation?->locked_at !== null)
            ->filter(fn (Finding $finding): bool => in_array(strtolower((string) $finding->type), ['weakness', 'recommendation', 'improvement', 'gap'], true));

        return $findings->map(function (Finding $finding) use ($evaluation, $organization, $createdBy, $workflow): CreatorAction {
            $existing = CreatorAction::query()
                ->where('evaluation_id', $evaluation->id)
                ->where('finding_id', $finding->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $workflow->create(
                $evaluation,
                $organization,
                $createdBy,
                $this->title($finding),
                $this->description($finding),
                $this->priority($finding),
                $finding,
            );
        })->values();
    }

    private function title(Finding $finding): string
    {
        return 'Address: '.trim((string) $finding->title);
    }

    private function description(Finding $finding): string
    {
        return 'Action derived from the evaluation finding: '.trim((string) $finding->description);
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
