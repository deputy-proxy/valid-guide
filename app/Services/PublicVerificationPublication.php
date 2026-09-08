<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationStatus;
use App\Models\PublicVerificationRecord;
use App\Models\Validation;
use Illuminate\Support\Str;

class PublicVerificationPublication
{
    public function publish(Validation $validation): PublicVerificationRecord
    {
        $validation->loadMissing([
            'productRelease.product.organization',
            'evaluation.standardVersion',
            'evaluation.criterionVotes.criterion',
        ]);

        if ($validation->status === ValidationStatus::Revoked) {
            throw new DomainStateTransitionException('A revoked validation cannot be newly published.');
        }

        $record = PublicVerificationRecord::query()->firstOrNew([
            'validation_id' => $validation->id,
        ]);

        $record->public_slug ??= Str::lower($validation->verification_identifier);
        $record->directory_visible = $record->directory_visible ?? true;
        $record->snapshot = $this->snapshot($validation);
        $record->published_at ??= now();
        $record->save();

        AuditLogger::record(
            event: 'public_verification.published',
            auditable: $record,
            after: [
                'validation_id' => $validation->id,
                'public_slug' => $record->public_slug,
                'status' => $validation->status->value,
            ],
        );

        return $record->refresh();
    }

    public function sync(Validation $validation): ?PublicVerificationRecord
    {
        $record = PublicVerificationRecord::query()
            ->where('validation_id', $validation->id)
            ->first();

        if ($record === null) {
            return null;
        }

        $record->snapshot = $this->snapshot($validation->fresh());
        $record->save();

        return $record->refresh();
    }

    /** @return array<string, mixed> */
    private function snapshot(Validation $validation): array
    {
        $release = $validation->productRelease;
        $product = $release->product;
        $evaluation = $validation->evaluation;

        return [
            'verification_identifier' => $validation->verification_identifier,
            'status' => $validation->status->value,
            'issued_at' => $validation->issued_at?->toIso8601String(),
            'product' => [
                'title' => $release->title_snapshot,
                'type' => $product->product_type->value,
                'creator' => $product->organization->name,
                'release_identifier' => $release->release_identifier,
                'version' => $release->version,
            ],
            'standard' => [
                'name' => $evaluation->standardVersion->name,
                'version' => $evaluation->standardVersion->version,
            ],
            'decision' => $evaluation->decision,
            'overall_score' => $evaluation->overall_score,
        ];
    }
}
