<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\EvaluationDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluationDecisionService
{
    public function decide(Evaluation $evaluation, User $decidedBy): EvaluationDecision
    {
        return DB::transaction(function () use ($evaluation, $decidedBy): EvaluationDecision {
            $evaluation = Evaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();

            if ($evaluation->status !== EvaluationStatus::ReadyForDecision) {
                throw new DomainStateTransitionException('An evaluation must be ready for decision before a final decision can be recorded.');
            }

            $auditorEvaluations = AuditorEvaluation::query()
                ->where('evaluation_id', $evaluation->getKey())
                ->where('status', 'submitted')
                ->whereNotNull('locked_at')
                ->with('assignment')
                ->get();

            $assignments = $evaluation->assignments()->get();
            $incompleteAssignments = $assignments->contains(
                fn ($assignment): bool => ! in_array($assignment->status, ['declined', 'cancelled'], true)
                    && ! $auditorEvaluations->contains('auditor_assignment_id', $assignment->id),
            );

            if ($incompleteAssignments) {
                throw new DomainStateTransitionException('Every non-cancelled auditor assignment must have a submitted evaluation before the final decision.');
            }

            $voterCount = $auditorEvaluations->unique('auditor_assignment_id')->count();

            if ($voterCount === 0 || $voterCount % 2 === 0) {
                throw new DomainStateTransitionException('Final evaluation decisions require a positive odd number of submitted Auditor evaluations.');
            }

            $criterionVoting = app(CriterionVoting::class);
            foreach ($auditorEvaluations as $auditorEvaluation) {
                $criterionVoting->record($auditorEvaluation);
            }

            $criteria = Criterion::query()
                ->where('standard_version_id', $evaluation->standard_version_id)
                ->orderBy('sequence')
                ->get();

            if ($criteria->isEmpty()) {
                throw new DomainStateTransitionException('An evaluation cannot be decided without methodology criteria.');
            }

            $criterionDecisions = [];
            $totalWeight = 0.0;
            $weightedScore = 0.0;
            $dimensionTotals = [];
            $dimensionWeights = [];
            $blockers = [];

            foreach ($criteria as $criterion) {
                $aggregate = $criterionVoting->aggregate($evaluation, $criterion->id);

                if ($aggregate['voter_count'] !== $voterCount) {
                    throw new DomainStateTransitionException(sprintf(
                        'Criterion %s does not have a vote from every submitted Auditor.',
                        $criterion->code,
                    ));
                }

                $winningVotes = $evaluation->criterionVotes()
                    ->where('criterion_id', $criterion->id)
                    ->where('decision', $aggregate['decision'])
                    ->with('criterionResult')
                    ->get();

                $score = $aggregate['decision'] === 'not_applicable'
                    ? null
                    : round((float) $winningVotes->avg(fn ($vote): float => (float) $vote->criterionResult->score), 2);

                if ($score === null && $aggregate['decision'] !== 'not_applicable') {
                    throw new DomainStateTransitionException(sprintf('Criterion %s has no numerical score.', $criterion->code));
                }

                $criterionDecisions[$criterion->code] = [
                    'criterion_id' => $criterion->id,
                    'decision' => $aggregate['decision'],
                    'score' => $score,
                    'counts' => $aggregate['counts'],
                ];

                if ($criterion->is_mandatory && ($score === null || $score < 75)) {
                    $blockers[] = sprintf('Mandatory criterion %s does not meet the 75/100 threshold.', $criterion->code);
                }

                if ($aggregate['decision'] === 'insufficient_evidence') {
                    $blockers[] = sprintf('Criterion %s has insufficient evidence.', $criterion->code);
                }

                if ($score === null) {
                    continue;
                }

                $weight = (float) $criterion->weight;
                $totalWeight += $weight;
                $weightedScore += $score * $weight;

                $dimension = $criterion->category;
                if ($dimension !== null && preg_match('/^D(?:[1-9]|10)$/', $dimension) === 1) {
                    $dimensionTotals[$dimension] = ($dimensionTotals[$dimension] ?? 0.0) + ($score * $weight);
                    $dimensionWeights[$dimension] = ($dimensionWeights[$dimension] ?? 0.0) + $weight;
                }
            }

            $overallScore = $totalWeight > 0 ? round($weightedScore / $totalWeight, 2) : null;

            if ($overallScore === null || $overallScore < 75) {
                $blockers[] = 'The weighted overall score is below 75/100.';
            }

            foreach ($dimensionWeights as $dimension => $weight) {
                $dimensionScore = round($dimensionTotals[$dimension] / $weight, 2);
                if ($dimensionScore < 60) {
                    $blockers[] = sprintf('%s is below the 60/100 core-dimension floor.', $dimension);
                }
            }

            $decision = empty($blockers) ? 'validated' : 'not_validated';
            $rationale = json_encode([
                'decision' => $decision,
                'overall_score' => $overallScore,
                'voter_count' => $voterCount,
                'criterion_decisions' => $criterionDecisions,
                'blockers' => $blockers,
            ], JSON_THROW_ON_ERROR);

            $evaluation->decision = $decision;
            $evaluation->overall_score = $overallScore;
            $evaluation->decision_rationale = $rationale;
            $evaluation->save();

            $decisionRecord = EvaluationDecision::query()->create([
                'evaluation_id' => $evaluation->id,
                'decision' => $decision,
                'rationale' => $rationale,
                'decided_by' => $decidedBy->id,
                'decided_at' => now(),
            ]);

            AuditLogger::record(
                event: 'evaluation.decision_recorded',
                auditable: $decisionRecord,
                after: [
                    'decision' => $decision,
                    'overall_score' => $overallScore,
                    'decided_by' => $decidedBy->id,
                ],
            );

            return $decisionRecord->refresh();
        });
    }
}
