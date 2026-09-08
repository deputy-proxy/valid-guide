<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationStatus;
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

    public function transition(Validation $validation, ValidationStatus $to, ?string $reason = null): Validation
    {
        return DB::transaction(function () use ($validation, $to, $reason): Validation {
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
            $validation->status = $to;
            $validation->status_reason = $reason;

            match ($to) {
                ValidationStatus::Suspended => $validation->suspended_at = $now,
                ValidationStatus::Revoked => $validation->revoked_at = $now,
                ValidationStatus::Superseded => $validation->superseded_at = $now,
                ValidationStatus::Active => null,
            };

            $validation->save();

            $badge = $validation->badge()->lockForUpdate()->first();

            if ($badge !== null) {
                $badge->status = $to;
                $badge->save();
            }

            (new PublicVerificationPublication)->sync($validation);

            AuditLogger::record(
                event: 'validation.status_changed',
                auditable: $validation,
                before: ['status' => $from->value],
                after: [
                    'status' => $to->value,
                    'reason' => $reason,
                ],
            );

            return $validation->refresh();
        });
    }
}
