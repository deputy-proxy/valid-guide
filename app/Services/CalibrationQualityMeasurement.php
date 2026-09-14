<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CriterionAssessment;
use App\Enums\EvaluationStatus;
use App\Models\AuditLog;
use App\Models\Evaluation;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CalibrationQualityMeasurement
{
    private const MINIMUM_SAMPLE_SIZE = 3;

    /**
     * @return array{
     *     standard_version_id:int,
     *     standard_version:string,
     *     status:string,
     *     completed_evaluations:int,
     *     locked_auditor_evaluations:int,
     *     criterion_results:int,
     *     insufficient_evidence:int,
     *     insufficient_evidence_rate:float|null,
     *     comparable_criterion_groups:int,
     *     agreement_rate:float|null,
     *     score_variance:float|null,
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

        /** @var array<string,array{criterion_id:int,assessments:array<int,string>,scores:array<int,float>}> $criterionGroups */
        $criterionGroups = [];
        /** @var array<int,string> $criterionNames */
        $criterionNames = [];
        /** @var array<string,int> $decisionOutcomes */
        $decisionOutcomes = [];
        $criterionResults = 0;
        $insufficientEvidence = 0;
        $anomalousResults = 0;
        $lockedAuditorEvaluations = 0;

        foreach ($evaluations as $evaluation) {
            $decision = $evaluation->getAttribute('decision');
            if (is_string($decision) && $decision !== '') {
                $decisionOutcomes[$decision] = ($decisionOutcomes[$decision] ?? 0) + 1;
            }

            foreach ($evaluation->auditorEvaluations as $auditorEvaluation) {
                $lockedAuditorEvaluations++;

                foreach ($auditorEvaluation->criterionResults as $result) {
                    $criterionResults++;
                    $assessmentValue = $result->getRawOriginal('assessment');
                    $assessment = is_string($assessmentValue)
                        ? CriterionAssessment::tryFrom($assessmentValue)
                        : null;
                    $criterionId = (int) $result->criterion_id;
                    $criterionNames[$criterionId] = (string) $result->criterion->name;
                    $groupKey = $evaluation->getKey().':'.$criterionId;

                    if (isset($criterionGroups[$groupKey]) === false) {
                        $criterionGroups[$groupKey] = [
                            'criterion_id' => $criterionId,
                            'assessments' => [],
                            'scores' => [],
                        ];
                    }

                    if ($assessment instanceof CriterionAssessment) {
                        $criterionGroups[$groupKey]['assessments'][] = $assessment->value;
                    } else {
                        $anomalousResults++;

                        continue;
                    }

                    if ($assessment === CriterionAssessment::InsufficientEvidence) {
                        $insufficientEvidence++;
                    }

                    if ($assessment->isScored()) {
                        $score = $result->score;
                        if (is_numeric($score) && (float) $score >= 0 && (float) $score <= 100) {
                            $criterionGroups[$groupKey]['scores'][] = (float) $score;
                        } else {
                            $anomalousResults++;
                        }
                    }
                }
            }
        }

        /** @var array<int,array{criterion_id:int,agreement:bool,scores:array<int,float>}> $comparableGroups */
        $comparableGroups = [];
        foreach ($criterionGroups as $group) {
            $assessments = array_values(array_unique($group['assessments']));

            if (array_key_exists(1, $assessments) === false) {
                continue;
            }

            $comparableGroups[] = [
                'criterion_id' => $group['criterion_id'],
                'agreement' => count(array_unique($assessments)) === 1,
                'scores' => $group['scores'],
            ];
        }

        $agreementCount = count(array_filter(
            $comparableGroups,
            static fn (array $group): bool => $group['agreement'],
        ));
        /** @var array<int,float> $scoreVariances */
        $scoreVariances = [];

        foreach ($comparableGroups as $group) {
            $scores = $group['scores'];
            if (array_key_exists(1, $scores) === false) {
                continue;
            }

            $mean = array_sum($scores) / count($scores);
            $scoreVariances[] = array_sum(array_map(
                static fn (float $score): float => ($score - $mean) ** 2,
                $scores,
            )) / count($scores);
        }

        /** @var array<int,array{evaluations:int,disagreements:int}> $criterionDisagreements */
        $criterionDisagreements = [];
        foreach ($comparableGroups as $group) {
            $criterionId = $group['criterion_id'];
            if (isset($criterionDisagreements[$criterionId]) === false) {
                $criterionDisagreements[$criterionId] = [
                    'evaluations' => 0,
                    'disagreements' => 0,
                ];
            }

            $criterionDisagreements[$criterionId]['evaluations']++;
            if ($group['agreement'] === false) {
                $criterionDisagreements[$criterionId]['disagreements']++;
            }
        }

        /** @var array<int,array{criterion:string,evaluations:int,disagreements:int,rate:float}> $recurringDisagreements */
        $recurringDisagreements = [];
        foreach ($criterionDisagreements as $criterionId => $data) {
            $evaluationsCount = $data['evaluations'];
            $disagreements = $data['disagreements'];
            if ($evaluationsCount < 2 || $disagreements < 2) {
                continue;
            }

            $recurringDisagreements[] = [
                'criterion' => $criterionNames[$criterionId] ?? 'Unknown criterion',
                'evaluations' => $evaluationsCount,
                'disagreements' => $disagreements,
                'rate' => round(($disagreements / $evaluationsCount) * 100, 2),
            ];
        }

        usort(
            $recurringDisagreements,
            static fn (array $left, array $right): int => $right['rate'] <=> $left['rate'],
        );

        $completedCount = $evaluations->count();
        $flags = [];
        if ($completedCount < self::MINIMUM_SAMPLE_SIZE) {
            $flags[] = 'insufficient_sample';
        }
        if ($anomalousResults > 0) {
            $flags[] = 'anomalous_data';
        }
        if ($recurringDisagreements !== []) {
            $flags[] = 'recurring_criterion_disagreement';
        }
        if ($completedCount >= self::MINIMUM_SAMPLE_SIZE && $comparableGroups === []) {
            $flags[] = 'no_auditor_comparison';
        }

        return [
            'standard_version_id' => (int) $standardVersion->getKey(),
            'standard_version' => (string) $standardVersion->version,
            'status' => $completedCount >= self::MINIMUM_SAMPLE_SIZE ? 'sufficient' : 'insufficient_data',
            'completed_evaluations' => $completedCount,
            'locked_auditor_evaluations' => $lockedAuditorEvaluations,
            'criterion_results' => $criterionResults,
            'insufficient_evidence' => $insufficientEvidence,
            'insufficient_evidence_rate' => $this->percentage($insufficientEvidence, $criterionResults),
            'comparable_criterion_groups' => count($comparableGroups),
            'agreement_rate' => $this->percentage($agreementCount, count($comparableGroups)),
            'score_variance' => $scoreVariances === [] ? null : round(array_sum($scoreVariances) / count($scoreVariances), 4),
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
            'calibration.reviewed',
            $standardVersion,
            after: [
                'status' => $metrics['status'],
                'completed_evaluations' => $metrics['completed_evaluations'],
                'review_flags' => $metrics['review_flags'],
            ],
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
}
