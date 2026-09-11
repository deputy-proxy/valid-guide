<?php

declare(strict_types=1);

use App\Enums\NotificationEventType;
use App\Enums\ValidationStatus;
use App\Models\Evaluation;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use App\Services\ValidationIssuance;
use App\Services\ValidationStateTransition;
use Illuminate\Notifications\DatabaseNotification;

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

test('refuses to issue validation for a not validated evaluation', function () {
    [$evaluation, $decider] = decisionFixture(70);

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    expect(fn () => app(ValidationIssuance::class)->issue($evaluation, $decider))
        ->toThrow(DomainStateTransitionException::class);
});

test('refuses to issue validation before evaluation completion', function () {
    [$evaluation, $decider] = decisionFixture();
    $evaluation->decision = 'validated';
    $evaluation->save();

    expect(fn () => app(ValidationIssuance::class)->issue($evaluation, $decider))
        ->toThrow(DomainStateTransitionException::class);
});

test('refuses to issue a second validation for the same evaluation', function () {
    [$evaluation, $decider] = decisionFixture();

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    app(ValidationIssuance::class)->issue($evaluation, $decider);

    expect(fn () => app(ValidationIssuance::class)->issue(Evaluation::findOrFail($evaluation->id), $decider))
        ->toThrow(DomainStateTransitionException::class);
});

test('requires a platform administrator to issue a validation', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $nonAdmin = User::factory()->create();

    expect(fn () => app(ValidationIssuance::class)->issue($evaluation, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});

test('prevents direct mutation of validation provenance and lifecycle state', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $validation = app(ValidationIssuance::class)->issue($evaluation, $decider);

    $validation->verification_identifier = 'VG-TAMPERED';
    expect(fn () => $validation->save())
        ->toThrow(DomainStateTransitionException::class);

    $validation->refresh();
    $validation->status = ValidationStatus::Suspended;
    $validation->status_reason = 'tampered';
    expect(fn () => $validation->save())
        ->toThrow(DomainStateTransitionException::class);
});

test('changes validation state only through the controlled transition service', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $validation = app(ValidationIssuance::class)->issue($evaluation, $decider);

    $transitioned = app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Suspended,
        $decider,
        'Material issue identified during post-validation review.',
    );

    expect($transitioned->status)->toBe(ValidationStatus::Suspended)
        ->and($transitioned->suspended_at)->not->toBeNull()
        ->and($transitioned->status_reason)->toBe('Material issue identified during post-validation review.')
        ->and($transitioned->badge()->first()->status)->toBe(ValidationStatus::Suspended);
});

test('issuing validation notifies the creator organization', function () {
    [$evaluation, $decider] = decisionFixture();
    $creator = User::factory()->create();
    $evaluation->request->organization->users()->attach($creator, ['role' => 'owner']);

    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $validation = app(ValidationIssuance::class)->issue($evaluation, $decider);

    $notification = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $creator->id)
        ->where('type', WorkflowNotification::class)
        ->where('data->event_type', NotificationEventType::ValidationIssued->value)
        ->latest()
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification?->data['event_type'])->toBe(NotificationEventType::ValidationIssued->value)
        ->and($notification?->data['context'])->toMatchArray([
            'validation_id' => $validation->id,
            'evaluation_id' => $evaluation->id,
            'verification_identifier' => $validation->verification_identifier,
        ]);
});
