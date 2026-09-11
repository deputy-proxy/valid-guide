<?php

declare(strict_types=1);

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionVotingMode;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ConflictDeclaration;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\CriterionVote;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\AuditorEvaluationSubmission;
use App\Services\CriterionVoting;
use App\Services\DomainStateTransitionException;

it('records a criterion vote only when the methodology designates collective determination', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture(CriterionVotingMode::Majority);
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $votes = app(CriterionVoting::class)->record($auditorEvaluation);

    expect($votes)->toHaveCount(1)
        ->and($votes->first()->criterion_result_id)->toBe($result->id)
        ->and($votes->first()->decision->value)->toBe('meets');
});

it('does not create votes for non-collective criteria', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $votes = app(CriterionVoting::class)->record($auditorEvaluation);

    expect($votes)->toHaveCount(0)
        ->and(CriterionVote::query()->count())->toBe(0);
});

it('aggregates a collective criterion by simple majority with three auditors and preserves minority counts', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture(CriterionVotingMode::Majority);
    $criterion = $result->criterion;
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    app(CriterionVoting::class)->record($auditorEvaluation);

    addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $criterion, 'meets', 2);
    addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $criterion, 'partially_meets', 3);

    $aggregate = app(CriterionVoting::class)->aggregate($auditorEvaluation->evaluation, $criterion->id);

    expect($aggregate['decision'])->toBe('meets')
        ->and($aggregate['voter_count'])->toBe(3)
        ->and($aggregate['counts'])->toMatchArray([
            'meets' => 2,
            'partially_meets' => 1,
        ]);
});

it('aggregates a collective criterion with five auditors by simple majority', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture(CriterionVotingMode::Majority);
    $criterion = $result->criterion;
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    app(CriterionVoting::class)->record($auditorEvaluation);

    addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $criterion, 'meets', 2);
    addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $criterion, 'meets', 3);
    addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $criterion, 'partially_meets', 4);
    addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $criterion, 'partially_meets', 5);

    $aggregate = app(CriterionVoting::class)->aggregate($auditorEvaluation->evaluation, $criterion->id);

    expect($aggregate['decision'])->toBe('meets')
        ->and($aggregate['voter_count'])->toBe(5)
        ->and($aggregate['counts'])->toMatchArray([
            'meets' => 3,
            'partially_meets' => 2,
        ]);
});

it('rejects majority aggregation for a non-collective criterion', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);

    expect(fn () => app(CriterionVoting::class)->aggregate($auditorEvaluation->evaluation, $result->criterion_id))
        ->toThrow(DomainStateTransitionException::class);
});

it('keeps independent non-collective assessments vote-free with one, three and five auditors', function (int $additionalAuditors) {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);

    for ($sequence = 2; $sequence <= $additionalAuditors + 1; $sequence++) {
        addSubmittedAuditorEvaluation($auditorEvaluation->evaluation, $result->criterion, 'meets', $sequence);
    }

    $votes = app(CriterionVoting::class)->record($auditorEvaluation);

    expect($votes)->toHaveCount(0)
        ->and(CriterionVote::query()->count())->toBe(0);
})->with([0, 2, 4]);

it('rejects aggregation with an even number of auditors', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture(CriterionVotingMode::Majority);
    $auditor = $auditorEvaluation->assignment->auditor;
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    app(CriterionVoting::class)->record($auditorEvaluation);

    $auditor = User::factory()->create();

    CriterionVote::create([
        'evaluation_id' => $auditorEvaluation->evaluation_id,
        'criterion_id' => $result->criterion_id,
        'criterion_result_id' => $result->id,
        'auditor_id' => $auditor->id,
        'decision' => 'meets',
    ]);

    expect(fn () => app(CriterionVoting::class)->aggregate($auditorEvaluation->evaluation, $result->criterion_id))
        ->toThrow(DomainStateTransitionException::class);
});

it('keeps recorded criterion votes immutable', function () {
    [$auditorEvaluation] = auditorEvaluationFixture(CriterionVotingMode::Majority);
    $auditor = $auditorEvaluation->assignment->auditor;
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $vote = app(CriterionVoting::class)->record($auditorEvaluation)->first();

    expect(fn () => $vote->update(['decision' => 'does_not_meet']))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $vote->delete())
        ->toThrow(DomainStateTransitionException::class);
});

function addSubmittedAuditorEvaluation(
    Evaluation $evaluation,
    Criterion $criterion,
    string $assessment,
    int $sequence,
): AuditorEvaluation {
    $auditor = User::factory()->create();
    $assignment = AuditorAssignment::create([
        'evaluation_id' => $evaluation->id,
        'auditor_id' => $auditor->id,
        'sequence' => $sequence,
        'status' => 'accepted',
        'assigned_at' => now(),
        'accepted_at' => now(),
    ]);

    ConflictDeclaration::create([
        'evaluation_id' => $evaluation->id,
        'auditor_assignment_id' => $assignment->id,
        'declaration_type' => 'assignment',
        'disclosure' => 'No known conflict.',
        'outcome' => 'cleared',
        'determined_by' => $auditor->id,
        'determined_at' => now(),
    ]);

    $auditorEvaluation = AuditorEvaluation::create([
        'evaluation_id' => $evaluation->id,
        'auditor_assignment_id' => $assignment->id,
        'version' => 1,
        'status' => 'draft',
        'evidence_sufficiency' => EvidenceSufficiency::Sufficient,
        'audience_promise_coherence' => AudiencePromiseCoherence::Coherent,
    ]);

    CriterionResult::create([
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'criterion_id' => $criterion->id,
        'assessment' => $assessment,
        'score' => $assessment === 'meets' ? 80 : 60,
        'rationale' => 'Sufficient evidence.',
        'confidence' => 90,
    ]);

    $submitted = app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    app(CriterionVoting::class)->record($submitted);

    return $submitted;
}
