<?php

use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationRequestStateTransition;

it('allows only explicitly defined evaluation request transitions', function () {
    $request = EvaluationRequest::factory()->create(['status' => EvaluationRequestStatus::Draft]);

    app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::AwaitingPayment);

    expect($request->fresh()->status)->toBe(EvaluationRequestStatus::AwaitingPayment);
});

it('rejects invalid evaluation request transitions', function () {
    $request = EvaluationRequest::factory()->create(['status' => EvaluationRequestStatus::Draft]);

    expect(fn () => app(EvaluationRequestStateTransition::class)->transition($request, EvaluationRequestStatus::Paid))
        ->toThrow(DomainStateTransitionException::class);
});
