<?php

declare(strict_types=1);

use App\Enums\ValidationStatus;
use App\Services\DomainStateTransitionException;
use App\Services\ValidationIssuance;
use App\Services\ValidationStateTransition;

function issuedValidation(): \App\Models\Validation
{
    [$evaluation, $decider] = decisionFixture();

    app(\App\Services\EvaluationDecisionService::class)->decide($evaluation, $decider);

    return app(ValidationIssuance::class)->issue($evaluation);
}

it('supports active to suspended and back to active while keeping the public record synchronized', function () {
    $validation = issuedValidation();

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Suspended, 'Temporary verification hold.');
    expect($validation->refresh()->status)->toBe(ValidationStatus::Suspended)
        ->and($validation->suspended_at)->not->toBeNull()
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Suspended)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Suspended->value);

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Active, 'Verification hold cleared.');
    expect($validation->refresh()->status)->toBe(ValidationStatus::Active)
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Active)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Active->value);
});

it('supports terminal revocation and prevents further transitions', function () {
    $validation = issuedValidation();

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Revoked, 'Material integrity issue confirmed.');

    expect($validation->refresh()->status)->toBe(ValidationStatus::Revoked)
        ->and($validation->revoked_at)->not->toBeNull()
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Revoked)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Revoked->value);

    expect(fn () => app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Active, 'Attempted reinstatement.'))
        ->toThrow(DomainStateTransitionException::class);
});

it('supports terminal supersession and prevents further transitions', function () {
    $validation = issuedValidation();

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Superseded, 'A newer validated release supersedes this validation.');

    expect($validation->refresh()->status)->toBe(ValidationStatus::Superseded)
        ->and($validation->superseded_at)->not->toBeNull()
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Superseded)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Superseded->value);
});

it('requires a reason for validation status changes', function () {
    $validation = issuedValidation();

    expect(fn () => app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Suspended, ''))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects direct mutation of badge identity and deletion', function () {
    $validation = issuedValidation();
    $badge = $validation->badge()->first();

    $badge->verification_identifier = 'VG-TAMPERED';

    expect(fn () => $badge->save())
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $badge->delete())
        ->toThrow(DomainStateTransitionException::class);
});
