<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AudiencePromiseCoherence;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditorEvaluation;
use Illuminate\Support\Facades\DB;

class AuditorEvaluationSubmission
{
    public function submit(AuditorEvaluation $auditorEvaluation): AuditorEvaluation
    {
        return DB::transaction(function () use ($auditorEvaluation): AuditorEvaluation {
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

            if ($assignment->status !== 'accepted') {
                throw new DomainStateTransitionException('An auditor assignment must be accepted before submission.');
            }

            $cleared = $assignment->conflictDeclarations()
                ->where('outcome', 'cleared')
                ->whereNotNull('determined_at')
                ->exists();

            if (!$cleared) {
                throw new DomainStateTransitionException(
                    'An auditor evaluation cannot be submitted until the assignment conflict declaration has been cleared.',
                );
            }

            if ($auditorEvaluation->criterionResults()->count() === 0) {
                throw new DomainStateTransitionException('An auditor evaluation must contain at least one criterion result before submission.');
            }

            $evaluation = $auditorEvaluation->evaluation()
                ->with('productRelease.product')
                ->firstOrFail();
            $productRelease = $evaluation->productRelease;

            if ($productRelease === null || $productRelease->product === null) {
                throw new DomainStateTransitionException('An auditor evaluation cannot be submitted without an evaluated product.');
            }

            $product = $productRelease->product;

            if ($auditorEvaluation->evidence_sufficiency === null) {
                throw new DomainStateTransitionException('An auditor evaluation must record an evidence sufficiency conclusion before submission.');
            }

            if ($auditorEvaluation->audience_promise_coherence === null) {
                throw new DomainStateTransitionException('An auditor evaluation must record an audience and promise coherence conclusion before submission.');
            }

            $claimedOutcomes = $product->claimed_outcomes;
            $hasCentralClaims = is_array($claimedOutcomes) && $claimedOutcomes !== [];

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
                    'evidence_sufficiency' => $auditorEvaluation->evidence_sufficiency->value,
                    'audience_promise_coherence' => $auditorEvaluation->audience_promise_coherence->value,
                ],
            );

            return $auditorEvaluation->refresh();
        });
    }
}
