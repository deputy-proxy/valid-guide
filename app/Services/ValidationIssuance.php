<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationStatus;
use App\Models\Validation;
use App\Models\ValidationBadge;

class ValidationIssuance
{
    public function issue($evaluation, $issuedBy): Validation
    {
        $verificationIdentifier = 'VG-' . strtoupper(bin2hex(random_bytes(4)));
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

        app(PublicVerificationPublication::class)->publish($validation->fresh());

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
    }
}
