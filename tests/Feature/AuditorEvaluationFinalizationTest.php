<?php

declare(strict_types=1);

use App\Enums\EvaluationStatus;
use App\Models\User;
use App\Services\AuditorEvaluationFinalization;
use App\Services\AuditorEvaluationSubmission;
use App\Services\DomainStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

test('finalizes a complete Auditor submission into the decision workflow', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);

    $evaluation = app(AuditorEvaluationFinalization::class)->finalize($auditorEvaluation, $auditor);

    expect($evaluation->status)->toBe(EvaluationStatus::ReadyForDecision)
        ->and($evaluation->fresh()->status)->toBe(EvaluationStatus::ReadyForDecision)
        ->and($auditorEvaluation->assignment->fresh()->status)->toBe('completed')
        ->and(DB::table('audit_logs')->where('event', 'auditor_evaluation.finalized')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('event', 'evaluation.status_changed')->count())->toBe(2);
});

test('finalization is idempotent after the evaluation is ready for decision', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $first = app(AuditorEvaluationFinalization::class)->finalize($auditorEvaluation, $auditor);
    $second = app(AuditorEvaluationFinalization::class)->finalize($auditorEvaluation->fresh(), $auditor);

    expect($first->id)->toBe($second->id)
        ->and($second->status)->toBe(EvaluationStatus::ReadyForDecision)
        ->and(DB::table('audit_logs')->where('event', 'auditor_evaluation.finalized')->count())->toBe(1);
});

test('finalization rejects a different user even when Auditor work is submitted', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    $otherUser = User::factory()->create();

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);

    expect(fn () => app(AuditorEvaluationFinalization::class)->finalize($auditorEvaluation, $otherUser))
        ->toThrow(AuthorizationException::class);

    expect($auditorEvaluation->evaluation->fresh()->status)->toBe(EvaluationStatus::InProgress);
});

test('finalization refuses an incomplete Auditor panel', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    $auditorEvaluation->evaluation->request()->update(['complexity' => 'complex']);

    expect(fn () => app(AuditorEvaluationFinalization::class)->finalize($auditorEvaluation, $auditor))
        ->toThrow(DomainStateTransitionException::class);

    expect($auditorEvaluation->evaluation->fresh()->status)->toBe(EvaluationStatus::InProgress)
        ->and($auditorEvaluation->assignment->fresh()->status)->toBe('accepted');
});

test('finalization can continue an evaluation already in internal review', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation, $auditor);
    DB::table('evaluations')->where('id', $auditorEvaluation->evaluation_id)->update([
        'status' => EvaluationStatus::InternalReview->value,
    ]);

    $evaluation = app(AuditorEvaluationFinalization::class)->finalize($auditorEvaluation->fresh(), $auditor);

    expect($evaluation->status)->toBe(EvaluationStatus::ReadyForDecision)
        ->and($auditorEvaluation->assignment->fresh()->status)->toBe('completed');
});
