<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Enums\ValidationStatus;
use App\Models\Evaluation;
use App\Models\User;
use App\Models\Validation;
use App\Models\ValidationBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ValidationIssuance
{
    public function issue(Evaluation $evaluation, User $issuedBy): Validation
    {
        if (! $issuedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only a platform administrator can issue a validation.');
        }

        return DB::transaction(function () use ($evaluation, $issuedBy): Validation {
            $evaluation = Evaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();

            if ($evaluation->status !== EvaluationStatus::Completed) {
                throw new DomainStateTransitionException('A validation can only be issued for a completed evaluation.');
            }

            if ($evaluation->decision !== 'validated') {
                throw new DomainStateTransitionException('A validation can only be issued for an evaluation that passed validation.');
            }

            $existing = Validation::query()
                ->where('evaluation_id', $evaluation->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new DomainStateTransitionException('This evaluation already has a validation record.');
            }

            $verificationIdentifier = $this->uniqueVerificationIdentifier();
            $issuedAt = now();

            $validation = Validation::query()->create([
                'product_release_id' => $evaluation->product_release_id,
                'evaluation_id' => $evaluation->id,
                'verification_identifier' => $verificationIdentifier,
                'issued_at' => $issuedAt,
                'status' => ValidationStatus::Active,
            ]);

            ValidationBadge::query()->create([
                'validation_id' => $validation->id,
                'verification_identifier' => $verificationIdentifier,
                'status' => ValidationStatus::Active,
                'issued_at' => $issuedAt,
                'embed_version' => '1',
            ]);

            (new PublicVerificationPublication)->publish($validation->fresh());

            AuditLogger::record(
                event: 'validation.issued',
                auditable: $validation,
                after: [
                    'evaluation_id' => $evaluation->id,
                    'product_release_id' => $evaluation->product_release_id,
                    'verification_identifier' => $verificationIdentifier,
                    'status' => ValidationStatus::Active->value,
                    'issued_by' => $issuedBy->id,
                ],
            );

            return $validation->refresh();
        });
    }

    private function uniqueVerificationIdentifier(): string
    {
        do {
            $identifier = 'VG-'.Str::upper(Str::random(16));
        } while (Validation::query()->where('verification_identifier', $identifier)->exists());

        return $identifier;
    }
}
