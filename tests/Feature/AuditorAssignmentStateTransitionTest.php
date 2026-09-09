<?php

declare(strict_types=1);

use App\Models\AuditorAnnualConflictDeclaration;
use App\Services\AuditorAssignmentStateTransition;
use App\Services\DomainStateTransitionException;

it('moves auditor assignments through explicit states', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update(['status' => 'offered']);

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $assignment->auditor_id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_at' => now(),
    ]);

    $service = app(AuditorAssignmentStateTransition::class);
    $service->transition($assignment, 'accepted');
    $service->transition($assignment->fresh(), 'completed');

    expect($assignment->fresh()->status)->toBe('completed')
        ->and($assignment->fresh()->accepted_at)->not->toBeNull()
        ->and($assignment->fresh()->completed_at)->not->toBeNull();
});

it('rejects accepting an assignment without current annual conflict clearance', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update(['status' => 'offered']);

    expect(fn () => app(AuditorAssignmentStateTransition::class)->transition($assignment, 'accepted'))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects accepting an assignment without cleared assignment conflict declaration', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update(['status' => 'offered']);

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $assignment->auditor_id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_at' => now(),
    ]);

    $assignment->conflictDeclarations()->update([
        'outcome' => 'disqualified',
        'determined_at' => now(),
    ]);

    expect(fn () => app(AuditorAssignmentStateTransition::class)->transition($assignment, 'accepted'))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects invalid auditor assignment transitions', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;

    expect(fn () => app(AuditorAssignmentStateTransition::class)->transition($assignment, 'declined'))
        ->toThrow(DomainStateTransitionException::class);
});
