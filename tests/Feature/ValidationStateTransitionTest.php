<?php

declare(strict_types=1);

use App\Enums\ValidationStatus;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use App\Services\ValidationIssuance;
use App\Services\ValidationStateTransition;

function issuedValidation(): array
{
    [$evaluation, $decider] = decisionFixture();

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    return [app(ValidationIssuance::class)->issue($evaluation, $decider), $decider];
}

it('supports active to suspended and back to active while keeping the public record synchronized', function () {
    [$validation, $admin] = issuedValidation();

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Suspended, $admin, 'Temporary verification hold.');
    expect($validation->refresh()->status)->toBe(ValidationStatus::Suspended)
        ->and($validation->suspended_at)->not->toBeNull()
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Suspended)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Suspended->value);

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Active, $admin, 'Verification hold cleared.');
    expect($validation->refresh()->status)->toBe(ValidationStatus::Active)
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Active)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Active->value);
});

it('supports terminal revocation and prevents further transitions', function () {
    [$validation, $admin] = issuedValidation();

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Revoked, $admin, 'Material integrity issue confirmed.');

    expect($validation->refresh()->status)->toBe(ValidationStatus::Revoked)
        ->and($validation->revoked_at)->not->toBeNull()
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Revoked)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Revoked->value);

    expect(fn () => app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Active, $admin, 'Attempted reinstatement.'))
        ->toThrow(DomainStateTransitionException::class);
});

it('supports terminal supersession and prevents further transitions', function () {
    [$validation, $admin] = issuedValidation();

    app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Superseded, $admin, 'A newer validated release supersedes this validation.');

    expect($validation->refresh()->status)->toBe(ValidationStatus::Superseded)
        ->and($validation->superseded_at)->not->toBeNull()
        ->and($validation->badge()->first()->status)->toBe(ValidationStatus::Superseded)
        ->and($validation->publicVerificationRecord()->first()->snapshot['status'])->toBe(ValidationStatus::Superseded->value);
});

it('requires a reason for validation status changes', function () {
    [$validation, $admin] = issuedValidation();

    expect(fn () => app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Suspended, $admin, ''))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires a platform administrator to change validation status', function () {
    [$validation] = issuedValidation();
    $nonAdmin = User::factory()->create(['platform_role' => null]);

    expect(fn () => app(ValidationStateTransition::class)->transition($validation, ValidationStatus::Suspended, $nonAdmin, 'Temporary verification hold.'))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects direct mutation of public verification record identity and deletion', function () {
    [$validation] = issuedValidation();
    $record = $validation->publicVerificationRecord()->first();

    $record->public_slug = 'tampered';

    expect(fn () => $record->save())
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $record->delete())
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects direct mutation of badge identity and deletion', function () {
    [$validation] = issuedValidation();
    $badge = $validation->badge()->first();

    $badge->verification_identifier = 'VG-TAMPERED';

    expect(fn () => $badge->save())
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $badge->delete())
        ->toThrow(DomainStateTransitionException::class);
});
