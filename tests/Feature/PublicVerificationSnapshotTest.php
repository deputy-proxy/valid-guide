<?php

declare(strict_types=1);

use App\Enums\ValidationStatus;
use App\Models\PublicVerificationRecord;
use App\Models\Validation;
use App\Services\PublicVerificationPublication;

it('publishes a complete public verification snapshot', function (): void {
    $validation = Validation::factory()->create([
        'status' => ValidationStatus::Issued,
    ]);

    $record = app(PublicVerificationPublication::class)->publish($validation->refresh());

    expect($record)->toBeInstanceOf(PublicVerificationRecord::class)
        ->and($record->snapshot)->toBeArray()
        ->and($record->snapshot['schema_version'])->toBe(1)
        ->and($record->snapshot)->toHaveKeys([
            'verification',
            'product',
            'standard',
            'result',
            'criteria',
            'findings',
        ]);
});

it('does not create a public record for a revoked validation', function (): void {
    $validation = Validation::factory()->create([
        'status' => ValidationStatus::Revoked,
    ]);

    expect(fn (): mixed => app(PublicVerificationPublication::class)->publish($validation->refresh()))
        ->toThrow(\App\Services\DomainStateTransitionException::class);
});
