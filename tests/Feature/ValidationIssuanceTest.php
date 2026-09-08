<?php

declare(strict_types=1);

use App\Enums\EvaluationStatus;
use App\Enums\ValidationStatus;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use App\Services\ValidationIssuance;

it('issues an active validation, badge, and public verification record atomically after a validated evaluation is completed', function () {
    [$evaluation, $decider] = decisionFixture();

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    $validation = app(ValidationIssuance::class)->issue($evaluation, $decider);
    $badge = $validation->badge()->first();
    $record = $validation->publicVerificationRecord()->first();

    expect($validation->status)->toBe(ValidationStatus::Active)
        ->and($validation->evaluation_id)->toBe($evaluation->id)
        ->and($validation->product_release_id)->toBe($evaluation->product_release_id)
        ->and($validation->issued_at)->not->toBeNull()
        ->and($validation->verification_identifier)->toStartWith('VG-')
        ->and($badge)->not->toBeNull()
        ->and($badge->status)->toBe(ValidationStatus::Active)
        ->and($badge->verification_identifier)->toBe($validation->verification_identifier)
        ->and($badge->issued_at->equalTo($validation->issued_at))->toBeTrue()
        ->and($record)->not->toBeNull()
        ->and($record->public_slug)->toBe(strtolower($validation->verification_identifier))
        ->and($record->published_at)->not->toBeNull()
        ->and($record->snapshot['verification_identifier'])->toBe($validation->verification_identifier)
        ->and($record->snapshot['status'])->toBe(ValidationStatus::Active->value)
        ->and($record->snapshot['decision'])->toBe('validated')
        ->and($record->snapshot['overall_score'])->toBe('80.00');
});

it('refuses to issue validation for a not validated evaluation', function () {
    [$evaluation, $decider] = decisionFixture(70);

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    expect(fn () => app(ValidationIssuance::class)->issue($evaluation, $decider))
        ->toThrow(DomainStateTransitionException::class);
});

it('refuses to issue validation before evaluation completion', function () {
    [$evaluation, $decider] = decisionFixture();
    $evaluation->status = EvaluationStatus::ReadyForDecision;
    $evaluation->decision = 'validated';
    $evaluation->save();

    expect(fn () => app(ValidationIssuance::class)->issue($evaluation, $decider))
        ->toThrow(DomainStateTransitionException::class);
});

it('refuses to issue a second validation for the same evaluation', function () {
    [$evaluation, $decider] = decisionFixture();

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    app(ValidationIssuance::class)->issue($evaluation, $decider);

    expect(fn () => app(ValidationIssuance::class)->issue(Evaluation::findOrFail($evaluation->id), $decider))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires a platform administrator to issue a validation', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $nonAdmin = User::factory()->create();

    expect(fn () => app(ValidationIssuance::class)->issue($evaluation, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});
