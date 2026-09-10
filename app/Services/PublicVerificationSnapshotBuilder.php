<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Models\Criterion;
use App\Models\CriterionVote;
use App\Models\Validation;

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

        $reportAbstract = $evaluation->report?->currentVersion?->abstract;

        return [
            'schema_version' => 1,
            'verification' => [
                'identifier' => $validation->verification_identifier,
                'status' => $validation->status->value,
                'issued_at' => $validation->issued_at?->toIso8601String(),
                'status_history' => [
                    'issued_at' => $validation->issued_at?->toIso8601String(),
                    'suspended_at' => $validation->suspended_at?->toIso8601String(),
                    'revoked_at' => $validation->revoked_at?->toIso8601String(),
                    'superseded_at' => $validation->superseded_at?->toIso8601String(),
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
            'standard' => [
                'name' => $standardVersion->standard->name,
                'version' => $standardVersion->version,
            ],
            'scope' => [
                'complexity' => $evaluation->request?->complexity?->value,
                'criteria' => $criteria->map(static fn (Criterion $criterion): array => [
                    'code' => $criterion->code,
                    'name' => $criterion->name,
                    'description' => $criterion->description,
                    'category' => $criterion->category,
                    'mandatory' => $criterion->is_mandatory,
                ])->all(),
            ],
            'result' => [
                'decision' => $evaluation->decision,
                'overall_score' => $evaluation->overall_score,
                'decision_rationale' => $evaluation->decision_rationale,
            ],
            'criteria' => $criteria->map(function (Criterion $criterion) use ($criterionResults, $criterionVotes): array {
                $results = $criterionResults->where('criterion_id', $criterion->id);
                $votes = $criterionVotes->get($criterion->id, collect());
                $scores = $results->pluck('score')->filter(static fn ($score): bool => $score !== null);
                $decision = null;

                if ($votes->isNotEmpty()) {
                    $counts = $votes
                        ->map(static fn (CriterionVote $vote): string => $vote->decision->value)
                        ->countBy()
                        ->sortDesc();
                    $decision = $counts->keys()->first();
                } elseif ($results->isNotEmpty()) {
                    $decision = $results->first()->assessment?->value;
                }

                return [
                    'code' => $criterion->code,
                    'name' => $criterion->name,
                    'assessment' => $decision,
                    'score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                    'voter_count' => $votes->unique('auditor_id')->count(),
                ];
            })->all(),
            'findings' => $evaluation->findings->map(static fn ($finding): array => [
                'criterion' => $finding->criterion?->name,
                'type' => $finding->type,
                'severity' => $finding->severity,
                'title' => $finding->title,
                'description' => $finding->description,
                'status' => $finding->status,
            ])->values()->all(),
            'strengths' => $evaluation->findings
                ->filter(static fn ($finding): bool => $finding->type === 'strength')
                ->map(static fn ($finding): array => [
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'criterion' => $finding->criterion?->name,
                ])->values()->all(),
            'weaknesses' => $evaluation->findings
                ->filter(static fn ($finding): bool => $finding->type === 'weakness')
                ->map(static fn ($finding): array => [
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'criterion' => $finding->criterion?->name,
                ])->values()->all(),
            'auditors' => [
                'count' => $auditors->count(),
                'disclosed' => $publicAuditors,
            ],
            'report' => [
                'abstract' => $reportAbstract,
            ],
            'visibility' => [
                'directory' => $directoryVisible,
                'full_report' => $fullReportVisible,
            ],
        ];
    }
}
