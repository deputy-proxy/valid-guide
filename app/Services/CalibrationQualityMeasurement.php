<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionAssessment;
use App\Enums\EvaluationStatus;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditLog;
use App\Models\Evaluation;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CalibrationQualityMeasurement
{
    private const MINIMUM_SAMPLE_SIZE = 5;

    /**
     * @return array{
     *     standard_version_id:int,
     *     standard_version:string,
     *     status:string,
     *     completed_evaluations:int,
     *     locked_auditor_evaluations:int,
     *     insufficient_evidence_rate:float|null,
     *     audience_promise_coherence_rate:float|null,
     *     criterion_agreement_rate:float|null,
     *     comparable_criterion_groups:int,
     *     overall_score_mean:float|null,
     *     overall_score_variance:float|null,
     *     validated_rate:float|null,
     *     decision_outcomes:array<string,int>,
     *     recurring_disagreements:array<int,array{criterion:string, evaluations:int, disagreements:int, rate:float}>,
     *     anomalous_results:int,
     *     review_flags:array<int,string>
     *     }
     */
    public function forStandardVersion(StandardVersion $standardVersion): array
    {
        $evaluations = Evaluation::query()
            ->where('standard_version_id', $standardVersion->getKey())
            ->where('status', EvaluationStatus::Completed)
            ->with([
                'auditorEvaluations' => fn ($query) => $query
                    ->whereNotNull('locked_at')
                    ->with(['criterionResults.criterion']),
            ])
            ->get();

        /** @var array<string,array{criterion:string,assessments:array<int,string>}> $criterionGroups */
        $criterionGroups = [];
        /** @var array<string,array{evaluations:int,disagreements:int,criterion:string}> $criterionDisagreements */
        $criterionDisagreements = [];
        /** @var array<string,int> $decisionOutcomes */
        $decisionOutcomes = [];
        /** @var array<int,float> $overallScores */
        $overallScores = [];
        $insufficientEvidence = 0;
        $coherentAuditorEvaluations = 0;
        $lockedAuditorEvaluations = 0;
        $anomalousResults = 0;

        foreach ($evaluations as $evaluation) {
            $decision = $evaluation->getAttribute('decision');
            if (is_string($decision) && $decision !== '') {
                $decisionOutcomes[$decision] = ($decisionOutcomes[$decision] ?? 0) + 1;
            }

            $overallScore = $evaluation->getAttribute('overall_score');
            if (is_numeric($overallScore) && (float) $overallScore >= 0 && (float) $overallScore <= 100) {
                $overallScores[] = (float) $overallScore;
            } else {
                $anomalousResults++;
            }

            foreach ($evaluation->auditorEvaluations as $auditorEvaluation) {
                $lockedAuditorEvaluations++;
                $evidenceSufficiency = $auditorEvaluation->getRawOriginal('evidence_sufficiency');
                if ($evidenceSufficiency === EvidenceSufficiency::Sufficient->value) {
                    // Valid sufficient observation.
                } elseif (is_string($evidenceSufficiency)) {
                    $insufficientEvidence++;
                } else {
                    $anomalousResults++;
                }

                $coherence = $auditorEvaluation->getRawOriginal('audience_promise_coherence');
                if ($coherence === AudiencePromiseCoherence::Coherent->value) {
                    $coherentAuditorEvaluations++;
                } elseif (is_string($coherence)) {
                    // Valid incoherent observation.
                } else {
                    $anomalousResults++;
                }

                foreach ($auditorEvaluation->criterionResults as $result) {
                    $assessmentValue = $result->getRawOriginal('assessment');
                    $assessment = is_string($assessmentValue)
                        ? CriterionAssessment::tryFrom($assessmentValue)
                        : null;
                    if ($assessment === null) {
                        $anomalousResults++;

                        continue;
                    }

                    if ($assessment->isScored()) {
                        $score = $result->getAttribute('score');
                        if (! is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                            $anomalousResults++;
                        }
                    }

                    $criterionId = (int) $result->criterion_id;
                    $criterionCode = (string) $result->criterion->code;
                    $groupKey = $evaluation->getKey().':'.$criterionId;
                    $criterionGroups[$groupKey]['criterion'] = $criterionCode;
                    $criterionGroups[$groupKey]['assessments'][] = $assessmentValue;
                }
            }
        }

        /** @var array<int,float> $agreementShares */
        $agreementShares = [];
        foreach ($criterionGroups as $group) {
            $assessments = $group['assessments'];
            if (array_key_exists(1, $assessments) === false) {
                continue;
            }

            $counts = array_count_values($assessments);
            $agreementShares[] = (max($counts) / count($assessments)) * 100;
            $criterionKey = $group['criterion'];
            $criterionDisagreements[$criterionKey]['criterion'] = $criterionKey;
            $criterionDisagreements[$criterionKey]['evaluations'] = ($criterionDisagreements[$criterionKey]['evaluations'] ?? 0) + 1;
            $criterionDisagreements[$criterionKey]['disagreements'] = ($criterionDisagreements[$criterionKey]['disagreements'] ?? 0)
                + (count($counts) > 1 ? 1 : 0);
        }

        /** @var array<int,array{criterion:string,evaluations:int,disagreements:int,rate:float}> $recurringDisagreements */
        $recurringDisagreements = [];
        foreach ($criterionDisagreements as $data) {
            if ($data['evaluations'] < self::MINIMUM_SAMPLE_SIZE) {
                continue;
            }

            $rate = round(($data['disagreements'] / $data['evaluations']) * 100, 2);
            if ($rate < 25) {
                continue;
            }

            $recurringDisagreements[] = [
                'criterion' => $data['criterion'],
                'evaluations' => $data['evaluations'],
                'disagreements' => $data['disagreements'],
                'rate' => $rate,
            ];
        }

        usort(
            $recurringDisagreements,
            static fn (array $left, array $right): int => $right['rate'] <=> $left['rate'],
        );

        $completedCount = $evaluations->count();
        $flags = [];
        if ($completedCount < self::MINIMUM_SAMPLE_SIZE) {
            $flags[] = 'insufficient_evaluation_sample';
        }
        if ($lockedAuditorEvaluations < self::MINIMUM_SAMPLE_SIZE) {
            $flags[] = 'insufficient_auditor_sample';
        }
        if (count($agreementShares) < self::MINIMUM_SAMPLE_SIZE) {
            $flags[] = 'insufficient_agreement_sample';
        }
        if ($recurringDisagreements !== []) {
            $flags[] = 'recurring_criterion_disagreement';
        }
        if ($anomalousResults > 0) {
            $flags[] = 'anomalous_data';
        }

        $status = $anomalousResults > 0
            ? 'anomalous_data'
            : ($completedCount >= self::MINIMUM_SAMPLE_SIZE ? 'sufficient' : 'insufficient_data');

        return [
            'standard_version_id' => (int) $standardVersion->getKey(),
            'standard_version' => (string) $standardVersion->version,
            'status' => $status,
            'completed_evaluations' => $completedCount,
            'locked_auditor_evaluations' => $lockedAuditorEvaluations,
            'insufficient_evidence_rate' => $this->percentage($insufficientEvidence, $lockedAuditorEvaluations),
            'audience_promise_coherence_rate' => $this->percentage($coherentAuditorEvaluations, $lockedAuditorEvaluations),
            'criterion_agreement_rate' => $this->average($agreementShares),
            'comparable_criterion_groups' => count($agreementShares),
            'overall_score_mean' => $this->average($overallScores),
            'overall_score_variance' => $this->populationVariance($overallScores),
            'validated_rate' => $this->percentage($decisionOutcomes['validated'] ?? 0, $completedCount),
            'decision_outcomes' => $decisionOutcomes,
            'recurring_disagreements' => $recurringDisagreements,
            'anomalous_results' => $anomalousResults,
            'review_flags' => $flags,
        ];
    }

    public function recordReview(StandardVersion $standardVersion, User $actor): AuditLog
    {
        if ($actor->isPlatformAdmin() === false) {
            throw new AuthorizationException('Only platform administrators may record calibration reviews.');
        }

        $metrics = $this->forStandardVersion($standardVersion);

        return AuditLogger::record(
            'validation.calibration_reviewed',
            $standardVersion,
            after: $metrics,
            metadata: [
                'standard_version_id' => $metrics['standard_version_id'],
                'methodology_version' => $metrics['standard_version'],
            ],
            actor: $actor,
        );
    }

    private function percentage(int $numerator, int $denominator): ?float
    {
        if ($denominator < self::MINIMUM_SAMPLE_SIZE) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function average(array $values): ?float
    {
        if (count($values) < self::MINIMUM_SAMPLE_SIZE) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function populationVariance(array $values): ?float
    {
        if (count($values) < self::MINIMUM_SAMPLE_SIZE) {
            return null;
        }

        $mean = array_sum($values) / count($values);

        return round(array_sum(array_map(
            static fn (float $value): float => ($value - $mean) ** 2,
            $values,
        )) / count($values), 2);
    }
}
