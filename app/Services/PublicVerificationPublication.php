<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationStatus;
use App\Models\PublicVerificationRecord;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Support\Str;

class PublicVerificationPublication
{
    public function __construct(
        private readonly PublicVerificationSnapshotBuilder $snapshotBuilder,
    ) {}

    public function publish(Validation $validation): PublicVerificationRecord
    {
        if ($validation->status === ValidationStatus::Revoked) {
            throw new DomainStateTransitionException('A revoked validation cannot be newly published.');
        }

        $record = PublicVerificationRecord::query()->firstOrNew([
            'validation_id' => $validation->id,
        ]);

        $record->public_slug ??= Str::lower($validation->verification_identifier);
        $record->directory_visible ??= true;
        $record->full_report_visible ??= false;
        $record->snapshot = $this->snapshotBuilder->build(
            $validation,
            $record->directory_visible,
            $record->full_report_visible,
        );
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

        $record->snapshot = $this->snapshotBuilder->build(
            $validation,
            $record->directory_visible,
            $record->full_report_visible,
        );
        $record->save();

        return $record->refresh();
    }

    public function setVisibility(
        PublicVerificationRecord $record,
        bool $directoryVisible,
        bool $fullReportVisible,
        User $changedBy,
    ): PublicVerificationRecord {
        if (! $changedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only a platform administrator can change public verification visibility.');
        }

        $record = PublicVerificationRecord::query()->whereKey($record->getKey())->firstOrFail();
        $record->directory_visible = $directoryVisible;
        $record->full_report_visible = $fullReportVisible;
        $record->snapshot = $this->snapshotBuilder->build(
            $record->validation()->firstOrFail(),
            $directoryVisible,
            $fullReportVisible,
        );
        $record->save();

        AuditLogger::record(
            event: 'public_verification.visibility_changed',
            auditable: $record,
            after: [
                'directory_visible' => $directoryVisible,
                'full_report_visible' => $fullReportVisible,
                'changed_by' => $changedBy->id,
            ],
        );

        return $record->refresh();
    }
}
