<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationStatus;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Support\Facades\DB;

class ValidationStateTransition
{
    /** @var array<string, list<ValidationStatus>> */
    private const TRANSITIONS = [
        'active' => [ValidationStatus::Suspended, ValidationStatus::Revoked, ValidationStatus::Superseded],
        'suspended' => [ValidationStatus::Active, ValidationStatus::Revoked, ValidationStatus::Superseded],
        'revoked' => [],
        'superseded' => [],
    ];

    public function transition(Validation $validation, ValidationStatus $to, User $changedBy, ?string $reason = null): Validation
    {
        if (! $changedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only a platform administrator can change validation status.');
        }

        return DB::transaction(function () use ($validation, $to, $changedBy, $reason): Validation {
            $validation = Validation::query()->whereKey($validation->getKey())->lockForUpdate()->firstOrFail();
            $from = $validation->status;

            if ($from === $to) {
                throw new DomainStateTransitionException('The validation is already in the requested state.');
            }

            if (! in_array($to, self::TRANSITIONS[$from->value] ?? [], true)) {
                throw new DomainStateTransitionException(sprintf(
                    'Invalid validation transition: %s -> %s.',
                    $from->value,
                    $to->value,
                ));
            }

            if ($reason === null || trim($reason) === '') {
                throw new DomainStateTransitionException('A reason is required when changing validation status.');
            }

            $now = now();
            $updates = [
                'status' => $to->value,
                'status_reason' => $reason,
                'updated_at' => $now,
            ];

            match ($to) {
                ValidationStatus::Suspended => $updates['suspended_at'] = $now,
                ValidationStatus::Revoked => $updates['revoked_at'] = $now,
                ValidationStatus::Superseded => $updates['superseded_at'] = $now,
                ValidationStatus::Active => null,
            };

            Validation::query()->whereKey($validation->getKey())->update($updates);

            $badge = $validation->badge()->lockForUpdate()->first();
            if ($badge !== null) {
                $badge->status = $to;
                $badge->save();
            }

            $validation->refresh();
            (new PublicVerificationPublication)->sync($validation);

            AuditLogger::record(
                event: 'validation.status_changed',
                auditable: $validation,
                after: [
                    'status' => $to->value,
                    'reason' => $reason,
                    'changed_by' => $changedBy->id,
                ],
                before: ['status' => $from->value],
            );

            return $validation;
        });
    }
}
