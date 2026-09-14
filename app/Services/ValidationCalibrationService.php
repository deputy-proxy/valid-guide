<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CriterionAssessment;
use App\Enums\EvaluationStatus;
use App\Models\AuditorEvaluation;
use App\Models\CriterionResult;
use App\Models\Evaluation;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Support\Collection;

final class ValidationCalibrationService
{
    private const MINIMUM_SAMPLE_SIZE = 5;

    private const DISAGREEMENT_REVIEW_THRESHOLD = 0.25;

    /**
     * @return list<array<string,mixed>>
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

        $report = null;
        foreach ($this->report() as $candidate) {
            if ($candidate['standard_version_id'] === (int) $standardVersion->getKey()) {
                $report = $candidate;
                break;
            }
        }

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
     * @phpstan-param Collection<int, Evaluation> $evaluations
     * @return array<string,mixed>
     */
    private function buildStandardReport(Collection $evaluations): array
    {
        $evaluation = $evaluations->first();
        if ($evaluation instanceof Evaluation === false) {
            throw new DomainStateTransitionException('Calibration cannot be calculated for an empty methodology population.');
        }

        $standardVersion = $evaluation->standardVersion;
        if ($standardVersion instanceof StandardVersion === false) {
            throw new DomainStateTransitionException('Calibration requires a persisted methodology Standard Version.');
        }

        $auditorEvaluations = $evaluations->flatMap(
            fn (Evaluation $currentEvaluation): Collection => $currentEvaluation->auditorEvaluations,
        );
        $scores = $evaluations
            ->pluck('overall_score')
            ->filter(fn ($score): bool => $score !== null)
            ->map(fn ($score): float => (float) $score)
            ->values();

        $insufficientCount = $auditorEvaluations->filter(
            fn (AuditorEvaluation $auditorEvaluation): bool => $auditorEvaluation->evidence_sufficiency?->value !== 'sufficient',
        )->count();
        $coherentCount = $auditorEvaluations->filter(
            fn (AuditorEvaluation $auditorEvaluation): bool => $auditorEvaluation->audience_promise_coherence?->value === 'coherent',
        )->count();

        $agreementUnits = [];
        $disagreements = [];

        foreach ($evaluations as $currentEvaluation) {
            $criterionGroups = $currentEvaluation->auditorEvaluations
                ->flatMap(fn (AuditorEvaluation $auditorEvaluation): Collection => $auditorEvaluation->criterionResults)
                ->groupBy('criterion_id');

            foreach ($criterionGroups as $criterionResults) {
                if ($criterionResults instanceof Collection === false || $criterionResults->count() < 2) {
                    continue;
                }

                $assessments = $criterionResults
                    ->pluck('assessment')
                    ->filter(fn ($assessment): bool => $assessment instanceof CriterionAssessment)
                    ->map(fn (CriterionAssessment $assessment): string => $assessment->value)
                    ->values();

                if ($assessments->isEmpty()) {
                    continue;
                }

                $counts = $assessments->countBy();
                $agreementUnits[] = (int) $counts->max() / $assessments->count();

                $criterionResult = $criterionResults->first();
                if ($criterionResult instanceof CriterionResult === false || $criterionResult->criterion === null) {
                    continue;
                }

                $code = $criterionResult->criterion->code;
                $disagreements[$code] ??= ['disagreement_count' => 0, 'sample_size' => 0];
                $disagreements[$code]['sample_size']++;
                if ($counts->count() > 1) {
                    $disagreements[$code]['disagreement_count']++;
                }
            }
        }

        $decisionOutcomes = [];
        foreach ($evaluations as $currentEvaluation) {
            $decision = (string) ($currentEvaluation->decision ?? 'unresolved');
            $decisionOutcomes[$decision] = ($decisionOutcomes[$decision] ?? 0) + 1;
        }

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
     * @phpstan-param list<float> $values
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
