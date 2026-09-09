<?php

declare(strict_types=1);

use App\Models\CriterionVote;
use App\Models\User;
use App\Services\AuditorEvaluationSubmission;
use App\Services\CriterionVoting;
use App\Services\DomainStateTransitionException;

it('records a criterion vote from a submitted auditor evaluation', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation);
    $votes = app(CriterionVoting::class)->record($auditorEvaluation);

    expect($votes)->toHaveCount(1)
        ->and($votes->first()->criterion_result_id)->toBe($result->id)
        ->and($votes->first()->decision->value)->toBe('meets');
});

it('aggregates criterion votes by simple majority and preserves minority counts', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation);
    app(CriterionVoting::class)->record($auditorEvaluation);

    $evaluation = $auditorEvaluation->evaluation;
    $criterion = $result->criterion;

    $minorityAuditor = User::factory()->create();
    $secondAuditor = User::factory()->create();

    CriterionVote::create([
        'evaluation_id' => $evaluation->id,
        'criterion_id' => $criterion->id,
        'criterion_result_id' => $result->id,
        'auditor_id' => $minorityAuditor->id,
        'decision' => 'meets',
    ]);

    CriterionVote::create([
        'evaluation_id' => $evaluation->id,
        'criterion_id' => $criterion->id,
        'criterion_result_id' => $result->id,
        'auditor_id' => $secondAuditor->id,
        'decision' => 'partially_meets',
    ]);

    $aggregate = app(CriterionVoting::class)->aggregate($evaluation, $criterion->id);

    expect($aggregate['decision'])->toBe('meets')
        ->and($aggregate['voter_count'])->toBe(3)
        ->and($aggregate['counts'])->toMatchArray([
            'meets' => 2,
            'partially_meets' => 1,
        ]);
});

it('rejects aggregation with an even number of auditors', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation);
    app(CriterionVoting::class)->record($auditorEvaluation);

    $evaluation = $auditorEvaluation->evaluation;
    $criterion = $result->criterion;
    $auditor = User::factory()->create();

    CriterionVote::create([
        'evaluation_id' => $evaluation->id,
        'criterion_id' => $criterion->id,
        'criterion_result_id' => $result->id,
        'auditor_id' => $auditor->id,
        'decision' => 'meets',
    ]);

    expect(fn () => app(CriterionVoting::class)->aggregate($evaluation, $criterion->id))
        ->toThrow(DomainStateTransitionException::class);
});

it('keeps recorded criterion votes immutable', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation);
    $vote = app(CriterionVoting::class)->record($auditorEvaluation)->first();

    expect(fn () => $vote->update(['decision' => 'does_not_meet']))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $vote->delete())
        ->toThrow(DomainStateTransitionException::class);
});
