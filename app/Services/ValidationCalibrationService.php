<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Support\Collection;

final class ValidationCalibrationService
{
    private const MINIMUM_SAMPLE_SIZE = 5;

    private const DISAGREEMENT_REVIEW_THRESHOLD = 0.25;

    /**
     * @return list<array{
     *     standard_version_id:int,
     *     standard_version:string,
     *     evaluations_completed:int,
     *     auditor_evaluations:int,
     *     insufficient_evidence_rate:float|null,
     *     audience_coherence_rate:float|null,
     *     criterion_agreement_rate:float|null,
     *     score_mean:float|null,
     *     score_variance:float|null,
     *     validated_rate:float|null,
     *     decision_outcomes:array<string,int>,
     *     recurring_disagreements:list<array{criterion:string,disagreement_count:int,sample_size:int,rate:float|null}>,
     *     review_flags:list<string>,
     * }>
     */
    public function report(): array
    {
        $evaluations = Evaluation::query()
            ->where('status', EvaluationStatus::Completed)
            ->with([
                'standardVersion',
                'auditorEvaluations' => fn ($query) => $query
                    ->where('status', 'submitted')
                    ->whereNotNull('locked_at')
                    ->with(['criterionResults.criterion']),
            ])
            ->get();

        /** @var Collection<int, Collection<int, Evaluation>> $byStandard */
        $byStandard = $evaluations->groupBy('standard_version_id');

        return $byStandard
            ->map(fn (Collection $standardEvaluations): array => $this->buildStandardReport($standardEvaluations))
            ->values()
            ->all();
    }

    public function recordReview(StandardVersion $standardVersion, User $actor): void
    {
        if ($actor->isPlatformAdmin() === false) {
            throw new DomainStateTransitionException('Only a platform administrator can record a calibration review.');
        }

        $report = collect($this->report())->firstWhere('standard_version_id', $standardVersion->getKey());

        AuditLogger::record(
            event: 'validation.calibration_reviewed',
            auditable: $standardVersion,
            after: $report,
            metadata: [
                'standard_version_id' => $standardVersion->getKey(),
            ],
            actor: $actor,
        );
    }

    /**
     * @param Collection<int, Evaluation> $evaluations
     * @return array{
     *     standard_version_id:int,
     *     standard_version:string,
     *     evaluations_completed:int,
     *     auditor_evaluations:int,
     *     insufficient_evidence_rate:float|null,
     *     audience_coherence_rate:float|null,
     *     criterion_agreement_rate:float|null,
     *     score_mean:float|null,
     *     score_variance:float|null,
     *     validated_rate:float|null,
     *     decision_outcomes:array<string,int>,
     *     recurring_disagreements:list<array{criterion:string,disagreement_count:int,sample_size:int,rate:float|null}>,
     *     review_flags:list<string>,
     * }
     */
    private function buildStandardReport(Collection $evaluations): array
    {
        /** @var StandardVersion $standardVersion */
        $standardVersion = $evaluations->firstOrFail()->standardVersion;
        $auditorEvaluations = $evaluations->flatMap(fn (Evaluation $evaluation): Collection => $evaluation->auditorEvaluations);
        $scores = $evaluations
            ->pluck('overall_score')
            ->filter(fn ($score): bool => $score !== null)
            ->map(fn ($score): float => (float) $score)
            ->values();

        $insufficientCount = $auditorEvaluations->filter(
            fn ($auditorEvaluation): bool => $auditorEvaluation->evidence_sufficiency?->value !== 'sufficient',
        )->count();
        $coherentCount = $auditorEvaluations->filter(
            fn ($auditorEvaluation): bool => $auditorEvaluation->audience_promise_coherence?->value === 'coherent',
        )->count();

        $agreementUnits = [];
        $disagreements = [];

        foreach ($evaluations as $evaluation) {
            $criterionGroups = $evaluation->auditorEvaluations
                ->flatMap(fn ($auditorEvaluation) => $auditorEvaluation->criterionResults)
                ->groupBy('criterion_id');

            foreach ($criterionGroups as $results) {
                $assessments = $results
                    ->pluck('assessment')
                    ->map(fn ($assessment): string => $assessment->value)
                    ->values();

                if ($assessments->isEmpty()) {
                    continue;
                }

                $counts = $assessments->countBy();
                $agreementUnits[] = $counts->max() / $assessments->count();

                $criterion = $results->first()->criterion;
                if ($criterion !== null && $counts->count() > 1) {
                    $code = $criterion->code;
                    $disagreements[$code] = ($disagreements[$code] ?? ['disagreement_count' => 0, 'sample_size' => 0]);
                    $disagreements[$code]['disagreement_count']++;
                    $disagreements[$code]['sample_size']++;
                } elseif ($criterion !== null) {
                    $code = $criterion->code;
                    $disagreements[$code] = ($disagreements[$code] ?? ['disagreement_count' => 0, 'sample_size' => 0]);
                    $disagreements[$code]['sample_size']++;
                }
            }
        }

        $decisionOutcomes = $evaluations
            ->countBy(fn (Evaluation $evaluation): string => (string) ($evaluation->decision ?? 'unresolved'))
            ->all();

        $sampleSize = $evaluations->count();
        $auditorSampleSize = $auditorEvaluations->count();
        $agreementSampleSize = count($agreementUnits);

        $recurringDisagreements = collect($disagreements)
            ->map(function (array $data, string $criterion): array {
                $rate = $data['sample_size'] >= self::MINIMUM_SAMPLE_SIZE
                    ? round($data['disagreement_count'] / $data['sample_size'], 4)
                    : null;

                return [
                    'criterion' => $criterion,
                    'disagreement_count' => $data['disagreement_count'],
                    'sample_size' => $data['sample_size'],
                    'rate' => $rate,
                ];
            })
            ->sortByDesc('disagreement_count')
            ->take(5)
            ->values()
            ->all();

        $reviewFlags = [];
        if ($sampleSize < self::MINIMUM_SAMPLE_SIZE) {
            $reviewFlags[] = 'insufficient_evaluation_sample';
        }
        if ($auditorSampleSize < self::MINIMUM_SAMPLE_SIZE) {
            $reviewFlags[] = 'insufficient_auditor_sample';
        }
        if ($agreementSampleSize < self::MINIMUM_SAMPLE_SIZE) {
            $reviewFlags[] = 'insufficient_agreement_sample';
        }

        foreach ($recurringDisagreements as $disagreement) {
            if ($disagreement['rate'] !== null && $disagreement['rate'] >= self::DISAGREEMENT_REVIEW_THRESHOLD) {
                $reviewFlags[] = 'recurring_criterion_disagreement';
                break;
            }
        }

        return [
            'standard_version_id' => (int) $standardVersion->getKey(),
            'standard_version' => (string) $standardVersion->version,
            'evaluations_completed' => $sampleSize,
            'auditor_evaluations' => $auditorSampleSize,
            'insufficient_evidence_rate' => $auditorSampleSize >= self::MINIMUM_SAMPLE_SIZE
                ? round($insufficientCount / $auditorSampleSize, 4)
                : null,
            'audience_coherence_rate' => $auditorSampleSize >= self::MINIMUM_SAMPLE_SIZE
                ? round($coherentCount / $auditorSampleSize, 4)
                : null,
            'criterion_agreement_rate' => $agreementSampleSize >= self::MINIMUM_SAMPLE_SIZE
                ? round(array_sum($agreementUnits) / $agreementSampleSize, 4)
                : null,
            'score_mean' => $sampleSize >= self::MINIMUM_SAMPLE_SIZE && $scores->isNotEmpty()
                ? round($scores->avg(), 2)
                : null,
            'score_variance' => $sampleSize >= self::MINIMUM_SAMPLE_SIZE && $scores->count() >= 2
                ? round($this->variance($scores->all()), 4)
                : null,
            'validated_rate' => $sampleSize >= self::MINIMUM_SAMPLE_SIZE
                ? round($evaluations->where('decision', 'validated')->count() / $sampleSize, 4)
                : null,
            'decision_outcomes' => $decisionOutcomes,
            'recurring_disagreements' => $recurringDisagreements,
            'review_flags' => array_values(array_unique($reviewFlags)),
        ];
    }

    /**
     * @param list<float> $values
     */
    private function variance(array $values): float
    {
        $mean = array_sum($values) / count($values);

        return array_sum(array_map(
            fn (float $value): float => ($value - $mean) ** 2,
            $values,
        )) / count($values);
    }
}
