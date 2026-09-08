<?php

declare(strict_types=1);

use App\Enums\ClarificationRequestType;
use App\Enums\DisputeGround;
use App\Enums\DisputeOutcome;
use App\Enums\EvaluationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\ClarificationWorkflow;
use App\Services\DisputeWorkflow;
use App\Services\DomainStateTransitionException;

function completedEvaluationFixture(): array
{
    [$evaluation, $platformAdmin] = decisionFixture();
    $evaluation->status = EvaluationStatus::Completed;
    $evaluation->decision = 'validated';
    $evaluation->overall_score = 80;
    $evaluation->completed_at = now();
    $evaluation->save();

    $organization = Organization::findOrFail($evaluation->request->organization_id);
    $creator = User::factory()->create();
    $organization->users()->attach($creator->id, ['role' => 'owner']);

    return [$evaluation->refresh(), $organization->refresh(), $creator, $platformAdmin];
}

test('clarification requests are tenant scoped and can be answered and closed', function () {
    [$evaluation, $organization, $creator, $platformAdmin] = completedEvaluationFixture();

    $request = app(ClarificationWorkflow::class)->submit(
        $evaluation,
        $organization,
        $creator,
        ClarificationRequestType::Report,
        'Please clarify the report wording.',
    );

    expect($request->status->value)->toBe('open')
        ->and($request->submitted_by)->toBe($creator->id);

    app(ClarificationWorkflow::class)->answer($request, $platformAdmin, 'The wording reflects the evaluated release.');
    $request->refresh();
    expect($request->status->value)->toBe('answered');

    app(ClarificationWorkflow::class)->close($request, $platformAdmin);
    expect($request->refresh()->status->value)->toBe('closed');
});

test('clarification request content cannot be changed after submission', function () {
    [$evaluation, $organization, $creator] = completedEvaluationFixture();

    $request = app(ClarificationWorkflow::class)->submit(
        $evaluation,
        $organization,
        $creator,
        ClarificationRequestType::Methodology,
        'What does criterion D1 mean?',
    );

    expect(fn () => $request->update(['message' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});

test('formal disputes accept only approved grounds', function () {
    [$evaluation, $organization, $creator] = completedEvaluationFixture();

    expect(fn () => app(DisputeWorkflow::class)->submit(
        $evaluation,
        $organization,
        $creator,
        ['pricing_is_unfair'],
        'The price is unfair.',
    ))->toThrow(DomainStateTransitionException::class);
});

test('formal dispute reviewers cannot be original evaluation participants', function () {
    [$evaluation, $organization, $creator, $platformAdmin] = completedEvaluationFixture();
    $dispute = app(DisputeWorkflow::class)->submit(
        $evaluation,
        $organization,
        $creator,
        [DisputeGround::ProceduralError],
        'The published procedure was not followed.',
    );

    $originalAuditor = $evaluation->assignments()->firstOrFail()->auditor;

    expect(fn () => app(DisputeWorkflow::class)->assignReviewer($dispute, $originalAuditor, $platformAdmin))
        ->toThrow(DomainStateTransitionException::class);
});

test('a process flaw starts a new evaluation without rewriting the original', function () {
    [$evaluation, $organization, $creator, $platformAdmin] = completedEvaluationFixture();
    $dispute = app(DisputeWorkflow::class)->submit(
        $evaluation,
        $organization,
        $creator,
        [DisputeGround::FlawedMethodologyApplication],
        'The methodology was materially misapplied.',
    );

    $reviewer = User::factory()->create();
    $review = app(DisputeWorkflow::class)->assignReviewer($dispute, $reviewer, $platformAdmin);
    app(DisputeWorkflow::class)->completeReview($review, $reviewer, 'The review confirms a material process flaw.');

    $result = app(DisputeWorkflow::class)->resolve(
        $dispute,
        $platformAdmin,
        DisputeOutcome::ProcessFlawed,
        'The original evaluation process was materially flawed; a fresh evaluation is required.',
    );

    expect($result['new_evaluation'])->not->toBeNull()
        ->and($result['new_evaluation']->id)->not->toBe($evaluation->id)
        ->and($evaluation->refresh()->status)->toBe(EvaluationStatus::Completed)
        ->and($result['dispute']->status->value)->toBe('resolved');
});
