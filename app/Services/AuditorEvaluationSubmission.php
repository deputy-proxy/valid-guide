<?php

declare(strict_types=1);

namespace App\Services;

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

            if ($auditorEvaluation->criterionResults()->count() === 0) {
                throw new DomainStateTransitionException('An auditor evaluation must contain at least one criterion result before submission.');
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
                ],
            );

            return $auditorEvaluation->refresh();
        });
    }
}
