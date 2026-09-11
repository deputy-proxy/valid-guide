<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AuditorEvaluationSubmission
{
    public function submit(AuditorEvaluation $auditorEvaluation, User $actor): AuditorEvaluation
    {
        return DB::transaction(function () use ($auditorEvaluation, $actor): AuditorEvaluation {
            $auditorEvaluation = AuditorEvaluation::query()
                ->whereKey($auditorEvaluation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($auditorEvaluation->locked_at !== null) {
                throw new DomainStateTransitionException('The auditor evaluation has already been submitted.');
            }

            if ($auditorEvaluation->status !== 'draft') {
                throw new DomainStateTransitionException('Only draft auditor evaluations can be submitted.');
            }

            $assignment = $auditorEvaluation->assignment()->lockForUpdate()->firstOrFail();

            if ((int) $assignment->auditor_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('Only the assigned Auditor can submit this evaluation.');
            }

            if ($assignment->status !== 'accepted') {
                throw new DomainStateTransitionException('An auditor assignment must be accepted before submission.');
            }

            $cleared = $assignment->conflictDeclarations()
                ->where('outcome', 'cleared')
                ->whereNotNull('determined_at')
                ->exists();

            if (! $cleared) {
                throw new DomainStateTransitionException(
                    'An auditor evaluation cannot be submitted until the assignment conflict declaration has been cleared.',
                );
            }

            $evaluation = $auditorEvaluation->evaluation()
                ->with([
                    'productRelease.product',
                    'standardVersion.criteria',
                ])
                ->firstOrFail();
            $productRelease = $evaluation->productRelease;

            if ($productRelease === null || $productRelease->product === null) {
                throw new DomainStateTransitionException('An auditor evaluation cannot be submitted without an evaluated product.');
            }

            $product = $productRelease->product;
            $applicability = app(CriterionApplicability::class);
            $criteria = $evaluation->standardVersion->criteria->filter(
                fn (Criterion $criterion): bool => $applicability->resolve($criterion, $product)['applicable'],
            );
            $criterionIds = $criteria->pluck('id');
            $resultCount = $auditorEvaluation->criterionResults()->count();

            if ($resultCount !== $criterionIds->count()) {
                throw new DomainStateTransitionException(
                    'An auditor evaluation must contain exactly one criterion result for every applicable criterion before submission.',
                );
            }

            if ($auditorEvaluation->criterionResults()->whereNotIn('criterion_id', $criterionIds)->exists()) {
                throw new DomainStateTransitionException(
                    'An auditor evaluation contains a criterion result that does not belong to its frozen standard version or is not applicable to the evaluated product.',
                );
            }

            if ($auditorEvaluation->evidence_sufficiency === null) {
                throw new DomainStateTransitionException('An auditor evaluation must record an evidence sufficiency conclusion before submission.');
            }

            if ($auditorEvaluation->audience_promise_coherence === null) {
                throw new DomainStateTransitionException('An auditor evaluation must record an audience and promise coherence conclusion before submission.');
            }

            $hasCentralClaims = is_array($product->claimed_outcomes) && $product->claimed_outcomes !== [];

            if ($hasCentralClaims && $auditorEvaluation->evidence()->count() === 0) {
                throw new DomainStateTransitionException('An auditor evaluation must contain evidence when the evaluated product has central claims.');
            }

            $submittedAt = now();

            $auditorEvaluation->criterionResults()->update([
                'submitted_at' => $submittedAt,
            ]);

            $auditorEvaluation->status = 'submitted';
            $auditorEvaluation->submitted_at = $submittedAt;
            $auditorEvaluation->locked_at = $submittedAt;
            $auditorEvaluation->save();

            AuditLogger::record(
                event: 'auditor_evaluation.submitted',
                auditable: $auditorEvaluation,
                after: [
                    'status' => 'submitted',
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'locked_at' => $submittedAt->toIso8601String(),
                    'submitted_by' => $actor->getKey(),
                    'evidence_sufficiency' => $auditorEvaluation->evidence_sufficiency->value,
                    'audience_promise_coherence' => $auditorEvaluation->audience_promise_coherence->value,
                ],
            );

            return $auditorEvaluation->refresh();
        });
    }
}
