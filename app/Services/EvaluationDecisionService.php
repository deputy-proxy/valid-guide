<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionVotingMode;
use App\Enums\EvaluationStatus;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\EvaluationDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluationDecisionService
{
    /** @return array{decision:string, overall_score:float|null, blockers:list<string>, criterion_decisions:array<string,array<string,mixed>>, voter_count:int} */
    public function assess(Evaluation $evaluation): array
    {
        $evaluation = Evaluation::query()
            ->with(['productRelease.product', 'standardVersion'])
            ->whereKey($evaluation->getKey())
            ->firstOrFail();

        if ($evaluation->status !== EvaluationStatus::ReadyForDecision) {
            throw new DomainStateTransitionException('An evaluation must be ready for decision before it can be assessed.');
        }

        $product = $evaluation->productRelease?->product;
        if ($product === null) {
            throw new DomainStateTransitionException('An evaluation cannot be decided without an evaluated product.');
        }

        $standardVersion = $evaluation->standardVersion;
        if ($standardVersion === null) {
            throw new DomainStateTransitionException('An evaluation cannot be decided without its methodology standard version.');
        }

        $mandatoryThreshold = $standardVersion->decisionThreshold('mandatory_minimum');
        $dimensionThreshold = $standardVersion->decisionThreshold('dimension_minimum');
        $overallThreshold = $standardVersion->decisionThreshold('overall_minimum');

        $auditorEvaluations = AuditorEvaluation::query()
            ->where('evaluation_id', $evaluation->getKey())
            ->where('status', 'submitted')
            ->whereNotNull('locked_at')
            ->get();

        $assignments = $evaluation->assignments()->get();
        $incompleteAssignments = $assignments->contains(
            fn ($assignment): bool => in_array($assignment->status, ['declined', 'cancelled'], true) === false
                && $auditorEvaluations->contains('auditor_assignment_id', $assignment->id) === false,
        );

        if ($incompleteAssignments) {
            throw new DomainStateTransitionException('Every non-cancelled auditor assignment must have a submitted evaluation before the final decision.');
        }

        $voterCount = $auditorEvaluations->unique('auditor_assignment_id')->count();

        if ($voterCount === 0 || $voterCount % 2 === 0) {
            throw new DomainStateTransitionException('Final evaluation decisions require a positive odd number of submitted Auditor evaluations.');
        }

        $blockers = [];

        foreach ($auditorEvaluations as $auditorEvaluation) {
            if ($auditorEvaluation->evidence_sufficiency !== EvidenceSufficiency::Sufficient) {
                $value = $auditorEvaluation->getRawOriginal('evidence_sufficiency') ?? 'unresolved';
                $blockers[] = sprintf('Auditor evaluation %d does not establish sufficient evidence for central product claims (%s).', $auditorEvaluation->id, $value);
            }

            if ($auditorEvaluation->audience_promise_coherence !== AudiencePromiseCoherence::Coherent) {
                $value = $auditorEvaluation->getRawOriginal('audience_promise_coherence') ?? 'unresolved';
                $blockers[] = sprintf('Auditor evaluation %d does not establish coherence with the stated audience and promise (%s).', $auditorEvaluation->id, $value);
            }
        }

        $hasCentralClaims = is_array($product->claimed_outcomes) && $product->claimed_outcomes !== [];

        if ($hasCentralClaims) {
            $missingEvidence = $auditorEvaluations->contains(
                fn (AuditorEvaluation $auditorEvaluation): bool => $auditorEvaluation->evidence()->exists() === false,
            );

            if ($missingEvidence) {
                $blockers[] = 'Central product claims do not have evidence coverage from every submitted Auditor evaluation.';
            }
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

        $criterionApplicability = app(CriterionApplicability::class);
        $criterionDecisions = [];
        $totalWeight = 0.0;
        $weightedScore = 0.0;
        $dimensionTotals = [];
        $dimensionWeights = [];
        $applicableDimensions = [];

        foreach ($criteria as $criterion) {
            $applicability = $criterionApplicability->resolve($criterion, $product);

            if ($applicability['applicable'] === false) {
                $criterionDecisions[$criterion->code] = [
                    'criterion_id' => $criterion->id,
                    'applicable' => false,
                    'decision' => 'not_applicable',
                    'score' => null,
                    'weight' => 0.0,
                    'mandatory' => false,
                ];

                continue;
            }

            $dimension = $criterion->category;
            if ($dimension !== null) {
                $applicableDimensions[$dimension] = true;
            }

            if ($criterion->voting_mode === CriterionVotingMode::Majority) {
                $aggregate = $criterionVoting->aggregate($evaluation, $criterion->id);

                if ($aggregate['voter_count'] !== $voterCount) {
                    throw new DomainStateTransitionException(sprintf('Applicable criterion %s does not have a vote from every submitted Auditor.', $criterion->code));
                }

                $decision = $aggregate['decision'];
                $counts = $aggregate['counts'];
                $winningVotes = $evaluation->criterionVotes()
                    ->where('criterion_id', $criterion->id)
                    ->where('decision', $decision)
                    ->with('criterionResult')
                    ->get();

                $score = in_array($decision, ['not_applicable', 'insufficient_evidence'], true)
                    ? null
                    : round((float) $winningVotes->avg(fn ($vote): float => (float) $vote->criterionResult->score), 2);
            } else {
                $results = collect();

                foreach ($auditorEvaluations as $auditorEvaluation) {
                    $result = $auditorEvaluation->criterionResults()->where('criterion_id', $criterion->id)->first();

                    if ($result === null) {
                        $decision = 'insufficient_evidence';
                        $score = null;
                        $counts = [];
                        $blockers[] = sprintf('Criterion %s is missing an Auditor result.', $criterion->code);
                        $results = collect();
                        break;
                    }

                    $results->push($result);
                }

                if ($results->isEmpty() && isset($decision) === false) {
                    $decision = 'insufficient_evidence';
                    $score = null;
                    $counts = [];
                } elseif ($results->isNotEmpty()) {
                    $counts = $results->countBy(fn ($result): string => $result->assessment->value)->all();
                    $decisions = $results->map(fn ($result): string => $result->assessment->value)->unique();

                    if ($decisions->count() !== 1) {
                        $decision = 'insufficient_evidence';
                        $score = null;
                        $blockers[] = sprintf('Criterion %s has conflicting Auditor assessments.', $criterion->code);
                    } else {
                        $decision = $decisions->first();
                        $score = in_array($decision, ['not_applicable', 'insufficient_evidence'], true)
                            ? null
                            : round((float) $results->avg(fn ($result): float => (float) $result->score), 2);
                    }
                }
            }

            $criterionDecisions[$criterion->code] = [
                'criterion_id' => $criterion->id,
                'applicable' => true,
                'decision' => $decision,
                'score' => $score,
                'weight' => $applicability['weight'],
                'mandatory' => $applicability['mandatory'],
                'counts' => $counts,
                'voting_mode' => $criterion->voting_mode->value,
            ];

            if ($applicability['mandatory'] && ($score === null || $score < $mandatoryThreshold)) {
                $blockers[] = sprintf(
                    'Mandatory criterion %s does not meet the %.0f/100 threshold.',
                    $criterion->code,
                    $mandatoryThreshold,
                );
            }

            if ($decision === 'insufficient_evidence') {
                $blockers[] = sprintf('Criterion %s has insufficient evidence.', $criterion->code);
            }

            if ($score === null) {
                continue;
            }

            $weight = $applicability['weight'];
            if ($weight <= 0) {
                continue;
            }

            $totalWeight += $weight;
            $weightedScore += $score * $weight;

            if ($dimension !== null) {
                $dimensionTotals[$dimension] = ($dimensionTotals[$dimension] ?? 0.0) + ($score * $weight);
                $dimensionWeights[$dimension] = ($dimensionWeights[$dimension] ?? 0.0) + $weight;
            }
        }

        foreach (array_keys($applicableDimensions) as $dimension) {
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

    public function decide(Evaluation $evaluation, User $decidedBy): EvaluationDecision
    {
        $this->authorizePlatformAdmin($decidedBy);

        return DB::transaction(function () use ($evaluation, $decidedBy): EvaluationDecision {
            $evaluation = Evaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();
            $assessment = $this->assess($evaluation);
            $rationale = json_encode($assessment, JSON_THROW_ON_ERROR);
            $decidedAt = now();

            $evaluation->decision = $assessment['decision'];
            $evaluation->overall_score = $assessment['overall_score'];
            $evaluation->decision_rationale = $rationale;
            $evaluation->save();

            $decisionRecord = EvaluationDecision::query()->create([
                'evaluation_id' => $evaluation->id,
                'decision' => $assessment['decision'],
                'rationale' => $rationale,
                'decided_by' => $decidedBy->id,
                'decided_at' => $decidedAt,
            ]);

            AuditLogger::record(
                event: 'evaluation.decision_recorded',
                auditable: $decisionRecord,
                after: [
                    'decision' => $assessment['decision'],
                    'overall_score' => $assessment['overall_score'],
                    'decided_by' => $decidedBy->id,
                ],
            );

            (new self)->completeEvaluation($evaluation);

            return $decisionRecord->refresh();
        });
    }

    private function authorizePlatformAdmin(User $user): void
    {
        if ($user->isPlatformAdmin() === false) {
            throw new DomainStateTransitionException('Only a platform administrator can record an evaluation decision.');
        }
    }

    private function completeEvaluation(Evaluation $evaluation): void
    {
        (new EvaluationStateTransition)->transition($evaluation, EvaluationStatus::Completed);
    }
}
