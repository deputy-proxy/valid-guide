<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementGuidanceCategory;
use App\Models\Evaluation;
use App\Models\Finding;
use App\Models\ImprovementGuidance;
use App\Models\Organization;
use App\Models\User;

final class ImprovementGuidancePlanner
{
    /** @return array<int, ImprovementGuidance> */
    public function generate(Evaluation $evaluation, Organization $organization, User $creator): array
    {
        $evaluation->loadMissing([
            'request',
            'report.currentVersion',
            'findings.auditorEvaluation',
        ]);

        $workflow = app(ImprovementGuidanceWorkflow::class);
        $actionWorkflow = app(CreatorActionWorkflow::class);
        $items = [];

        foreach ($evaluation->findings as $finding) {
            if (! $this->isActionableAndVisible($finding)) {
                continue;
            }

            $existing = ImprovementGuidance::query()
                ->where('evaluation_id', $evaluation->id)
                ->where('finding_id', $finding->id)
                ->first();

            if ($existing !== null) {
                if ($existing->requires_action && ! $existing->creatorAction()->exists()) {
                    $actionWorkflow->create(
                        $evaluation,
                        $organization,
                        $creator,
                        $existing->title,
                        $existing->guidance,
                        $existing->priority,
                        $finding,
                        $existing,
                    );
                }

                $items[] = $existing;

                continue;
            }

            $guidance = $workflow->create(
                $evaluation,
                $organization,
                $creator,
                $finding->title,
                $this->guidanceFor($finding),
                $finding->description,
                $this->categoryFor($finding),
                $this->priorityFor($finding),
                $finding,
                null,
                true,
            );

            $actionWorkflow->create(
                $evaluation,
                $organization,
                $creator,
                $guidance->title,
                $guidance->guidance,
                $guidance->priority,
                $finding,
                $guidance,
            );

            $items[] = $guidance;
        }

        return $items;
    }

    private function isActionableAndVisible(Finding $finding): bool
    {
        $type = strtolower((string) $finding->type);

        if (! in_array($type, ['weakness', 'recommendation', 'improvement', 'gap'], true)) {
            return false;
        }

        return $finding->auditor_evaluation_id === null || $finding->auditorEvaluation?->locked_at !== null;
    }

    private function categoryFor(Finding $finding): ImprovementGuidanceCategory
    {
        return match (strtolower((string) $finding->type)) {
            'gap' => ImprovementGuidanceCategory::Evidence,
            'recommendation' => ImprovementGuidanceCategory::Delivery,
            'improvement' => ImprovementGuidanceCategory::Structure,
            default => ImprovementGuidanceCategory::Content,
        };
    }

    private function priorityFor(Finding $finding): CreatorActionPriority
    {
        return match (strtolower((string) $finding->severity)) {
            'critical' => CreatorActionPriority::Critical,
            'high' => CreatorActionPriority::High,
            'medium' => CreatorActionPriority::Medium,
            default => CreatorActionPriority::Low,
        };
    }

    private function guidanceFor(Finding $finding): string
    {
        return sprintf(
            'Review and address the finding "%s" using the evaluation evidence and the context of the evaluated product.',
            $finding->title,
        );
    }
}
