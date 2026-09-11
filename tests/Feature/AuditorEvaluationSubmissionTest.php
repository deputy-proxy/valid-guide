<?php

declare(strict_types=1);

use App\Models\Criterion;
use App\Models\User;
use App\Services\AuditorEvaluationSubmission;
use App\Services\ConflictDeclarationDecision;
use App\Services\DomainStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

test('submits and locks an auditor evaluation atomically', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    $submitted = app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $fresh = $submitted->fresh();
    expect($fresh->status)->toBe('submitted')->and($fresh->submitted_at)->not->toBeNull()->and($fresh->locked_at)->not->toBeNull()->and($result->fresh()->submitted_at)->not->toBeNull()->and(DB::table('audit_logs')->where('event', 'auditor_evaluation.submitted')->count())->toBe(1);
});

test('rejects submission by a different user', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $otherUser = User::factory()->create();

    expect(fn () => app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $otherUser))
        ->toThrow(AuthorizationException::class);

    expect($auditorEvaluation->fresh()->status)->toBe('draft');
});

test('rejects submission for an unaccepted assignment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    $auditorEvaluation->assignment()->update(['status' => 'offered']);
    expect(fn () => app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor))->toThrow(DomainStateTransitionException::class);
});

test('rejects submission without required decision gate conclusions', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    $auditorEvaluation->update([
        'evidence_sufficiency' => null,
        'audience_promise_coherence' => null,
    ]);

    expect(fn () => app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor))
        ->toThrow(DomainStateTransitionException::class);
});

test('rejects submission when a frozen standard criterion has no result', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    $standardVersion = $auditorEvaluation->evaluation->standardVersion;

    Criterion::create([
        'standard_version_id' => $standardVersion->id,
        'code' => 'TEST-02',
        'name' => 'Missing result criterion',
        'sequence' => 2,
        'weight' => 10,
        'is_mandatory' => false,
        'voting_mode' => 'individual',
    ]);

    expect(fn () => app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor))
        ->toThrow(DomainStateTransitionException::class);
});

test('rejects submission when a criterion result belongs to another standard version', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    $otherVersion = $auditorEvaluation->evaluation->standardVersion->replicate();
    $otherVersion->version = '9.9';
    $otherVersion->status = 'draft';
    $otherVersion->save();

    $otherCriterion = $otherVersion->criteria()->create([
        'code' => 'OTHER-01',
        'name' => 'Other criterion',
        'sequence' => 1,
        'weight' => 10,
        'is_mandatory' => false,
        'voting_mode' => 'individual',
    ]);

    $result->update(['criterion_id' => $otherCriterion->id]);

    expect(fn () => app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor))
        ->toThrow(DomainStateTransitionException::class);
});

test('prevents changes and deletion after auditor evaluation submission', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $auditorEvaluation->refresh();
    $result->refresh();
    expect(fn () => $auditorEvaluation->update(['status' => 'draft']))->toThrow(DomainStateTransitionException::class);
    expect(fn () => $result->update(['score' => 50]))->toThrow(DomainStateTransitionException::class);
    expect(fn () => $result->delete())->toThrow(DomainStateTransitionException::class);
    expect(fn () => $auditorEvaluation->delete())->toThrow(DomainStateTransitionException::class);
});

test('allows an explicit conflict decision and keeps it immutable', function () {
    [, , $declaration] = auditorEvaluationFixture();
    $decisionMaker = User::factory()->create(['platform_role' => 'admin']);
    DB::table('conflict_declarations')->where('id', $declaration->id)->update(['outcome' => 'potential_conflict', 'determined_by' => null, 'determined_at' => null]);
    $declaration->refresh();
    $decided = app(ConflictDeclarationDecision::class)->decide($declaration, 'cleared', $decisionMaker);
    $decided->refresh();
    expect($decided->outcome)->toBe('cleared')->and($decided->determined_by)->toBe($decisionMaker->id)->and($decided->determined_at)->not->toBeNull();
    expect(fn () => $decided->update(['outcome' => 'disqualified']))->toThrow(DomainStateTransitionException::class);
});
