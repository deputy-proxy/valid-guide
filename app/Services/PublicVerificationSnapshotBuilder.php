<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Validation;

class PublicVerificationSnapshotBuilder
{
    /** @return array<string, mixed> */
    public function build(Validation $validation): array
    {
        $validation->loadMissing([
            'productRelease.product.organization',
            'evaluation.standardVersion.standard',
            'evaluation.criterionVotes.criterion',
            'evaluation.findings.criterion',
        ]);

        $release = $validation->productRelease;
        $product = $release->product;
        $evaluation = $validation->evaluation;

        return [
            'schema_version' => 1,
            'verification' => [
                'identifier' => $validation->verification_identifier,
                'status' => $validation->status->value,
                'issued_at' => $validation->issued_at?->toIso8601String(),
                'suspended_at' => $validation->suspended_at?->toIso8601String(),
                'revoked_at' => $validation->revoked_at?->toIso8601String(),
                'superseded_at' => $validation->superseded_at?->toIso8601String(),
                'status_reason' => $validation->status_reason,
            ],
            'product' => [
                'title' => $release->title_snapshot,
                'type' => $product->product_type->value,
                'creator' => $product->organization->name,
                'release_identifier' => $release->release_identifier,
                'version' => $release->version,
            ],
            'standard' => [
                'name' => $evaluation->standardVersion->standard->name,
                'version' => $evaluation->standardVersion->version,
            ],
            'result' => [
                'decision' => $evaluation->decision,
                'overall_score' => $evaluation->overall_score,
            ],
            'criteria' => $evaluation->criterionVotes->map(
                static fn ($vote): array => [
                    'criterion' => [
                        'id' => $vote->criterion->id,
                        'name' => $vote->criterion->name,
                    ],
                    'assessment' => $vote->assessment?->value,
                    'score' => $vote->score,
                    'rationale' => $vote->rationale,
                ],
            )->values()->all(),
            'findings' => $evaluation->findings->map(
                static fn ($finding): array => [
                    'criterion' => $finding->criterion?->name,
                    'type' => $finding->type,
                    'severity' => $finding->severity,
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'status' => $finding->status,
                ],
            )->values()->all(),
        ];
    }
}
