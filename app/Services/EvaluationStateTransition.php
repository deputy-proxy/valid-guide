<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use Illuminate\Support\Facades\DB;

class EvaluationStateTransition
{
    /** @var array<string, list<EvaluationStatus>> */
    private const TRANSITIONS = [
        'pending' => [EvaluationStatus::InProgress, EvaluationStatus::Withdrawn],
        'in_progress' => [EvaluationStatus::InternalReview, EvaluationStatus::Withdrawn],
        'internal_review' => [EvaluationStatus::ReadyForDecision, EvaluationStatus::InProgress, EvaluationStatus::Withdrawn],
        'ready_for_decision' => [EvaluationStatus::Completed, EvaluationStatus::InternalReview, EvaluationStatus::Withdrawn],
        'completed' => [],
        'withdrawn' => [],
    ];

    public function transition(Evaluation $evaluation, EvaluationStatus $to): Evaluation
    {
        $from = $evaluation->status;

        if ($from === $to) {
            throw new DomainStateTransitionException('The evaluation is already in the requested state.');
        }

        if (! in_array($to, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new DomainStateTransitionException(sprintf(
                'Invalid evaluation transition: %s -> %s.',
                $from->value,
                $to->value,
            ));
        }

        if ($to === EvaluationStatus::Completed && blank($evaluation->decision)) {
            throw new DomainStateTransitionException(
                'An evaluation cannot be completed before an evaluation decision has been recorded.',
            );
        }

        return DB::transaction(function () use ($evaluation, $from, $to): Evaluation {
            $evaluation = Evaluation::query()->lockForUpdate()->findOrFail($evaluation->getKey());
            $now = now();
            $updates = [
                'status' => $to->value,
                'updated_at' => $now,
            ];

            if ($to === EvaluationStatus::InProgress && $evaluation->started_at === null) {
                $updates['started_at'] = $now;
            }

            if ($to === EvaluationStatus::Completed) {
                $updates['completed_at'] = $now;
            }

            Evaluation::query()->whereKey($evaluation->getKey())->update($updates);

            $evaluation->refresh();

            AuditLogger::record(
                event: 'evaluation.status_changed',
                auditable: $evaluation,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
            );

            return $evaluation;
        });
    }
}
