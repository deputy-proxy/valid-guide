<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CriterionAssessment;
use App\Enums\CriterionVotingMode;
use App\Exceptions\DomainStateTransitionException;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\Evaluation;
use Illuminate\Support\Collection;

class EvaluationDecisionService
{
    public function __construct(
        private readonly CriterionVoting $criterionVoting,
    ) {}

    /**
     * Resolve the final decision for an evaluation.
     *
     * @return array{decision:string, overall_score:float|null, blockers:list<string>, criterion_decisions:array<string, array{decision:string, score:float|null, counts:array<string,int>, blocker:string|null, voting_mode:string}>, voter_count:int}
     */
    public function decide(Evaluation $evaluation): array
    {
        $auditorEvaluations = $evaluation->auditorEvaluations()
            ->where('status', 'submitted')
            ->with('criterionResults')
            ->get();

        $auditorCount = $auditorEvaluations->count();

        if ($auditorCount < 1 || $auditorCount % 2 === 0) {
            throw new DomainStateTransitionException('A final decision requires a positive odd number of submitted Auditor evaluations.');
        }

        $standardVersion = $evaluation->standardVersion;
        $criteria = $standardVersion->criteria()->get();

        $criterionDecisions = [];
        $blockers = [];
        $weightedScore = 0.0;
        $totalWeight = 0.0;
        $dimensionTotals = [];
        $dimensionWeights = [];
        $dimensionThreshold = 0.0;

        foreach ($criteria as $criterion) {
            $criterionDecision = $this->resolveCriterionAssessment(
                $evaluation,
                $criterion,
                $auditorEvaluations,
                $auditorCount,
                $this->criterionVoting,
            );

            $criterionDecisions[$criterion->code] = [
                ...$criterionDecision,
                'voting_mode' => $criterion->voting_mode->value,
            ];

            if ($criterionDecision['blocker'] !== null) {
                $blockers[] = $criterionDecision['blocker'];
            }

            if ($criterionDecision['score'] !== null && $criterion->weight > 0) {
                $weightedScore += $criterionDecision['score'] * $criterion->weight;
                $totalWeight += $criterion->weight;

                $dimension = $criterion->category;
                $dimensionTotals[$dimension] = ($dimensionTotals[$dimension] ?? 0.0) + ($criterionDecision['score'] * $criterion->weight);
                $dimensionWeights[$dimension] = ($dimensionWeights[$dimension] ?? 0.0) + $criterion->weight;
            }
        }

        $dimensionThreshold = (float) ($standardVersion->dimension_threshold ?? 0);

        foreach (array_keys($dimensionTotals) as $dimension) {
            if (isset($dimensionWeights[$dimension]) === false || $dimensionWeights[$dimension] <= 0) {
                continue;
            }

            $dimensionScore = round($dimensionTotals[$dimension] / $dimensionWeights[$dimension], 2);

            if ($dimensionScore < $dimensionThreshold) {
                $blockers[] = sprintf(
                    '%s is below the %.0f/100 core-dimension floor.',
                    $dimension,
                    $dimensionThreshold,
                );
            }
        }

        $activeDisqualifier = $evaluation->findings()
            ->where(function ($query): void {
                $query->where('severity', 'critical')->orWhere('type', 'disqualifier');
            })
            ->whereNotIn('status', ['resolved', 'closed'])
            ->exists();

        if ($activeDisqualifier) {
            $blockers[] = 'An active disqualifying finding exists.';
        }

        $overallThreshold = (float) ($standardVersion->overall_threshold ?? 0);
        $overallScore = $totalWeight > 0 ? round($weightedScore / $totalWeight, 2) : null;

        if ($overallScore === null || $overallScore < $overallThreshold) {
            $blockers[] = sprintf('The weighted overall score is below %.0f/100.', $overallThreshold);
        }

        return [
            'decision' => empty($blockers) ? 'validated' : 'not_validated',
            'overall_score' => $overallScore,
            'blockers' => array_values(array_unique($blockers)),
            'criterion_decisions' => $criterionDecisions,
            'voter_count' => $voterCount,
        ];
    }

    /**
     * @param Collection<int, AuditorEvaluation> $auditorEvaluations
     * @return array{decision:string, score:float|null, counts:array<string,int>, blocker:string|null}
     */
    private function resolveCriterionAssessment(
        Evaluation $evaluation,
        Criterion $criterion,
        Collection $auditorEvaluations,
        int $auditorCount,
        CriterionVoting $criterionVoting,
    ): array {
        if ($criterion->voting_mode === CriterionVotingMode::Majority) {
            $aggregate = $criterionVoting->aggregate($evaluation, $criterion->id);

            if ($aggregate['voter_count'] !== $auditorCount) {
                throw new DomainStateTransitionException(sprintf('Collective criterion %s does not have a vote from every submitted Auditor.', $criterion->code));
            }

            $winningVotes = $evaluation->criterionVotes()
                ->where('criterion_id', $criterion->id)
                ->where('decision', $aggregate['decision'])
                ->with('criterionResult')
                ->get();

            $score = in_array($aggregate['decision'], ['not_applicable', 'insufficient_evidence'], true)
                ? null
                : round((float) $winningVotes->avg(fn ($vote): float => (float) $vote->criterionResult->score), 2);

            return [
                'decision' => $aggregate['decision'],
                'score' => $score,
                'counts' => $aggregate['counts'],
                'blocker' => $aggregate['decision'] === 'insufficient_evidence'
                    ? sprintf('Criterion %s has insufficient evidence.', $criterion->code)
                    : null,
            ];
        }

        $results = collect();
        foreach ($auditorEvaluations as $auditorEvaluation) {
            $result = $auditorEvaluation->criterionResults()->where('criterion_id', $criterion->id)->first();

            if ($result === null) {
                return [
                    'decision' => 'insufficient_evidence',
                    'score' => null,
                    'counts' => [],
                    'blocker' => sprintf('Criterion %s is missing an Auditor result.', $criterion->code),
                ];
            }

            $results->push($result);
        }

        $decisions = $results->map(fn ($result): string => $result->decision->value)->unique()->values();

        if ($decisions->count() !== 1) {
            return [
                'decision' => 'insufficient_evidence',
                'score' => null,
                'counts' => $results->countBy(fn ($result): string => $result->decision->value)->all(),
                'blocker' => sprintf('Criterion %s has conflicting Auditor assessments.', $criterion->code),
            ];
        }

        $decision = $decisions->first();
        $score = in_array($decision, ['not_applicable', 'insufficient_evidence'], true)
            ? null
            : round((float) $results->avg(fn ($result): float => (float) $result->score), 2);

        return [
            'decision' => $decision,
            'score' => $score,
            'counts' => $results->countBy(fn ($result): string => $result->decision->value)->all(),
            'blocker' => $decision === 'insufficient_evidence'
                ? sprintf('Criterion %s has insufficient evidence.', $criterion->code)
                : null,
        ];
    }
}
