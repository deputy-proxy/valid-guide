<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Models\AuditorEvaluation;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class AuditorEvaluationFinalization
{
    public function finalize(AuditorEvaluation $auditorEvaluation, User $actor): Evaluation
    {
        return DB::transaction(function () use ($auditorEvaluation, $actor): Evaluation {
            $lockedAuditorEvaluation = AuditorEvaluation::query()
                ->whereKey($auditorEvaluation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $assignment = $lockedAuditorEvaluation->assignment()->lockForUpdate()->firstOrFail();

            if ((int) $assignment->auditor_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('Only the assigned Auditor can finalize this submission.');
            }

            if ($lockedAuditorEvaluation->status !== 'submitted' || $lockedAuditorEvaluation->locked_at === null) {
                throw new DomainStateTransitionException('Only a submitted and locked Auditor evaluation can be finalized.');
            }

            $evaluation = Evaluation::query()
                ->whereKey($lockedAuditorEvaluation->evaluation_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $assignment->evaluation_id !== (int) $evaluation->getKey()) {
                throw new DomainStateTransitionException('The Auditor assignment does not belong to the evaluated Evaluation.');
            }

            if ($evaluation->status === EvaluationStatus::Completed || $evaluation->status === EvaluationStatus::ReadyForDecision) {
                return $evaluation->refresh();
            }

            if (! in_array($evaluation->status, [EvaluationStatus::InProgress, EvaluationStatus::InternalReview], true)) {
                throw new DomainStateTransitionException(sprintf(
                    'Auditor submission cannot be finalized while the Evaluation is %s.',
                    $evaluation->status->value,
                ));
            }

            app(AuditorStaffing::class)->assertSubmittedCount($evaluation);

            $requiredAssignments = $evaluation->assignments()
                ->whereNotIn('status', ['declined', 'cancelled'])
                ->lockForUpdate()
                ->get();

            foreach ($requiredAssignments as $requiredAssignment) {
                $hasSubmission = $evaluation->auditorEvaluations()
                    ->where('auditor_assignment_id', $requiredAssignment->getKey())
                    ->where('status', 'submitted')
                    ->whereNotNull('locked_at')
                    ->exists();

                if (! $hasSubmission) {
                    throw new DomainStateTransitionException(
                        'The Evaluation cannot enter the decision workflow until every active Auditor assignment has submitted locked work.',
                    );
                }
            }

            foreach ($requiredAssignments as $requiredAssignment) {
                if ($requiredAssignment->status === 'accepted') {
                    app(AuditorAssignmentStateTransition::class)->transition($requiredAssignment, 'completed');
                }
            }

            if ($evaluation->status === EvaluationStatus::InProgress) {
                $evaluation = app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::InternalReview);
            }

            if ($evaluation->status === EvaluationStatus::InternalReview) {
                $evaluation = app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::ReadyForDecision);
            }

            AuditLogger::record(
                event: 'auditor_evaluation.finalized',
                auditable: $lockedAuditorEvaluation,
                after: [
                    'status' => $lockedAuditorEvaluation->status,
                    'evaluation_status' => $evaluation->status->value,
                    'finalized_by' => $actor->getKey(),
                ],
            );

            return $evaluation->refresh();
        });
    }
}
