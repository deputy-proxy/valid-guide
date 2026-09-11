<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Enums\OrganizationRole;
use App\Models\Evaluation;
use App\Models\ImprovementGuidance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CreatorImprovementGuidanceAccess
{
    /** @return array<string, mixed> */
    public function show(User $user, int $evaluationId): array
    {
        $evaluation = Evaluation::query()
            ->with([
                'request.organization',
                'productRelease.product',
                'report.currentVersion',
                'improvementGuidances.finding',
                'improvementGuidances.criterionResult.criterion',
                'improvementGuidances.creatorAction',
            ])
            ->findOrFail($evaluationId);

        $organization = $evaluation->request?->organization;
        if ($organization === null || ! $this->canAccess($user, $organization->id)) {
            throw new AuthorizationException('You are not authorized to access improvement guidance for this evaluation.');
        }

        if ($evaluation->status !== EvaluationStatus::Completed || $evaluation->report?->creator_visible_at === null) {
            throw new AuthorizationException('Improvement guidance is not yet available to creators.');
        }

        $items = $evaluation->improvementGuidances
            ->filter(fn (ImprovementGuidance $guidance): bool => $guidance->creator_visible_at !== null)
            ->sortByDesc(fn (ImprovementGuidance $guidance): int => $this->priorityRank($guidance))
            ->map(fn (ImprovementGuidance $guidance): array => [
                'id' => $guidance->id,
                'category' => $guidance->category->value,
                'title' => $guidance->title,
                'guidance' => $guidance->guidance,
                'rationale' => $guidance->rationale,
                'priority' => $guidance->priority->value,
                'applicability' => $guidance->applicability,
                'status' => $guidance->status->value,
                'finding_id' => $guidance->finding?->id,
                'finding_title' => $guidance->finding?->title,
                'criterion_id' => $guidance->criterionResult?->criterion?->id,
                'criterion_code' => $guidance->criterionResult?->criterion?->code,
                'action_id' => $guidance->creatorAction?->id,
                'action_status' => $guidance->creatorAction?->status?->value,
                'published_at' => $guidance->creator_visible_at?->toISOString(),
            ])
            ->values()
            ->all();

        $product = $evaluation->productRelease?->product;

        return [
            'evaluation' => ['id' => $evaluation->id],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->getAttribute('name'),
            ],
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'completed' => count(array_filter($items, fn (array $item): bool => $item['status'] === 'completed')),
            ],
        ];
    }

    private function canAccess(User $user, int $organizationId): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $membership = $user->organizations()->whereKey($organizationId)->first();
        if ($membership === null) {
            return false;
        }

        return in_array(
            (string) $membership->pivot->getAttribute('role'),
            [
                OrganizationRole::Owner->value,
                OrganizationRole::Admin->value,
                OrganizationRole::Editor->value,
            ],
            true,
        );
    }

    private function priorityRank(ImprovementGuidance $guidance): int
    {
        return match ($guidance->priority->value) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }
}
