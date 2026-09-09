<?php

declare(strict_types=1);

use App\Services\AuditorCompensationService;
use App\Services\DomainStateTransitionException;

it('makes compensation payable after timely assignment completion', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'due_at' => now()->addDay(),
    ]);
    $admin = \App\Models\User::factory()->create(['platform_role' => 'admin']);

    $compensation = app(AuditorCompensationService::class)->assign($assignment, $admin, 15000, 'EUR');
    $compensation = app(AuditorCompensationService::class)->finalize($compensation);

    expect($compensation->status)->toBe('payable')
        ->and($compensation->amount_minor)->toBe(15000)
        ->and($compensation->currency)->toBe('EUR');
});

it('forfeits compensation when an assignment is completed after its deadline', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'due_at' => now()->subMinute(),
    ]);
    $admin = \App\Models\User::factory()->create(['platform_role' => 'admin']);

    $compensation = app(AuditorCompensationService::class)->assign($assignment, $admin, 15000, 'EUR');
    $compensation = app(AuditorCompensationService::class)->finalize($compensation);

    expect($compensation->status)->toBe('forfeited');
});

it('does not allow compensation amount to be changed after assignment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $admin = \App\Models\User::factory()->create(['platform_role' => 'admin']);
    $compensation = app(AuditorCompensationService::class)->assign($auditorEvaluation->assignment, $admin, 15000, 'EUR');

    expect(fn () => $compensation->update(['amount_minor' => 1]))
        ->toThrow(DomainStateTransitionException::class);
});
