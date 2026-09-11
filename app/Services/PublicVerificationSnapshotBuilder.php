<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Models\Criterion;
use App\Models\CriterionVote;
use App\Models\Finding;
use App\Models\Validation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PublicVerificationSnapshotBuilder
{
    /** @return array<string, mixed> */
    public function build(
        Validation $validation,
        bool $directoryVisible = true,
        bool $fullReportVisible = false,
    ): array {
        $validation->loadMissing([
            'productRelease.product.organization',
            'evaluation.standardVersion.standard',
            'evaluation.standardVersion.criteria',
            'evaluation.request',
            'evaluation.auditorEvaluations.assignment.auditor.auditorProfile',
            'evaluation.auditorEvaluations.criterionResults.criterion',
            'evaluation.criterionVotes.criterion',
            'evaluation.findings.criterion',
            'evaluation.report.currentVersion',
        ]);

        $release = $validation->productRelease;
        $product = $release->product;
        $evaluation = $validation->evaluation;
        $standardVersion = $evaluation->standardVersion;

        /** @var Collection<int, Criterion> $criteria */
        $criteria = $standardVersion->criteria->sortBy('sequence')->values();
        $criterionResults = $evaluation->auditorEvaluations
            ->flatMap(static fn ($auditorEvaluation) => $auditorEvaluation->criterionResults);
        $criterionVotes = $evaluation->criterionVotes->groupBy('criterion_id');

        $auditors = $evaluation->auditorEvaluations
            ->map(static fn ($auditorEvaluation) => $auditorEvaluation->assignment?->auditor)
            ->filter()
            ->unique('id')
            ->values();

        $publicAuditors = $auditors
            ->filter(static fn ($auditor) => $auditor->auditorProfile?->status === AuditorProfileStatus::Approved)
            ->map(static fn ($auditor): array => [
                'name' => $auditor->name,
                'bio' => $auditor->auditorProfile?->bio,
                'credentials' => $auditor->auditorProfile?->credentials,
            ])
            ->values()
            ->all();

        /** @var Collection<int, Finding> $findings */
        $findings = $evaluation->findings;
        $status = $validation->status->value;
        $decision = $evaluation->decision;
        $overallScore = $evaluation->overall_score;
        $reportVersion = $evaluation->report?->currentVersion;

        return [
            'schema_version' => 1,
            'verification_identifier' => $validation->verification_identifier,
            'status' => $status,
            'issued_at' => $this->formatDate($validation->issued_at),
            'decision' => $decision,
            'overall_score' => $overallScore,
            'verification' => [
                'identifier' => $validation->verification_identifier,
                'status' => $status,
                'issued_at' => $this->formatDate($validation->issued_at),
                'status_history' => [
                    'issued_at' => $this->formatDate($validation->issued_at),
                    'suspended_at' => $this->formatDate($validation->suspended_at),
                    'revoked_at' => $this->formatDate($validation->revoked_at),
                    'superseded_at' => $this->formatDate($validation->superseded_at),
                    'reason' => $validation->status_reason,
                ],
            ],
            'product' => [
                'title' => $release->title_snapshot,
                'type' => $product->product_type->value,
                'creator' => $product->organization->name,
                'release_identifier' => $release->release_identifier,
                'version' => $release->version,
            ],
            'suitability' => [
                'audiences' => $this->jsonList($product->getRawOriginal('matching_audiences')),
                'goals' => $this->jsonList($product->getRawOriginal('matching_goals')),
                'subject_area' => $product->subject_area,
                'language' => $product->language,
            ],
            'standard' => [
                'name' => $standardVersion->standard->name,
                'version' => $standardVersion->version,
            ],
            'scope' => [
                'complexity' => $this->enumValue($evaluation->request?->complexity),
                'criteria' => $criteria->map(static fn (Criterion $criterion): array => [
                    'code' => $criterion->code,
                    'name' => $criterion->name,
                    'description' => $criterion->description,
                    'category' => $criterion->category,
                    'mandatory' => $criterion->is_mandatory,
                ])->all(),
            ],
            'result' => [
                'decision' => $decision,
                'overall_score' => $overallScore,
                'decision_rationale' => $evaluation->decision_rationale,
            ],
            'criteria' => $criteria->map(function (Criterion $criterion) use ($criterionResults, $criterionVotes): array {
                $results = $criterionResults->where('criterion_id', $criterion->id);
                $votes = $criterionVotes->get($criterion->id, collect());
                $scores = $results->pluck('score')->filter(static fn ($score): bool => $score !== null);
                $decision = null;

                if ($votes->isNotEmpty()) {
                    $counts = $votes
                        ->map(fn (CriterionVote $vote): ?string => $this->enumValue($vote->decision))
                        ->filter()
                        ->countBy()
                        ->sortDesc();
                    $decision = $counts->keys()->first();
                } elseif ($results->isNotEmpty()) {
                    $decision = $this->enumValue($results->first()->assessment);
                }

                return [
                    'code' => $criterion->code,
                    'name' => $criterion->name,
                    'assessment' => $decision,
                    'score' => $scores->isNotEmpty() ? (float) round($scores->avg(), 2) : null,
                    'voter_count' => $votes->unique('auditor_id')->count(),
                ];
            })->all(),
            'findings' => $findings->map(static fn (Finding $finding): array => [
                'criterion' => $finding->criterion?->name,
                'type' => $finding->type,
                'severity' => $finding->severity,
                'title' => $finding->title,
                'description' => $finding->description,
                'status' => $finding->status,
            ])->values()->all(),
            'strengths' => $findings
                ->filter(static fn (Finding $finding): bool => $finding->type === 'strength')
                ->map(static fn (Finding $finding): array => [
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'criterion' => $finding->criterion?->name,
                ])->values()->all(),
            'weaknesses' => $findings
                ->filter(static fn (Finding $finding): bool => $finding->type === 'weakness')
                ->map(static fn (Finding $finding): array => [
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'criterion' => $finding->criterion?->name,
                ])->values()->all(),
            'auditors' => [
                'count' => $auditors->count(),
                'disclosed' => $publicAuditors,
            ],
            'report' => [
                'abstract' => $reportVersion?->abstract,
                'content' => $fullReportVisible ? $reportVersion?->content_structure : null,
            ],
            'visibility' => [
                'directory' => $directoryVisible,
                'full_report' => $fullReportVisible,
            ],
        ];
    }

    /** @return list<mixed>|null */
    private function jsonList(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : null;
    }

    private function formatDate(CarbonInterface|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? $value->toIso8601String()
            : CarbonImmutable::parse($value)->toIso8601String();
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
