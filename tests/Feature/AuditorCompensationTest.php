<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AuditorCompensationService;
use App\Services\DomainStateTransitionException;
use App\Services\PayoutService;

it('makes compensation payable after timely assignment completion', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'due_at' => now()->addDay(),
    ]);
    $admin = User::factory()->create(['platform_role' => 'admin']);

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
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $compensation = app(AuditorCompensationService::class)->assign($assignment, $admin, 15000, 'EUR');
    $compensation = app(AuditorCompensationService::class)->finalize($compensation);

    expect($compensation->status)->toBe('forfeited');
});

it('does not allow compensation amount to be changed after assignment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $compensation = app(AuditorCompensationService::class)->assign($auditorEvaluation->assignment, $admin, 15000, 'EUR');

    expect(fn () => $compensation->update(['amount_minor' => 1]))
        ->toThrow(DomainStateTransitionException::class);
});

it('does not allow the same compensation to be included in multiple payouts', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'due_at' => now()->addDay(),
    ]);
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $compensation = app(AuditorCompensationService::class)->assign($assignment, $admin, 15000, 'EUR');
    $compensation = app(AuditorCompensationService::class)->finalize($compensation);
    $payoutService = app(PayoutService::class);

    $payoutService->create($admin, $compensation);

    expect(fn () => $payoutService->create($admin, $compensation))
        ->toThrow(DomainStateTransitionException::class);
});

it('does not allow payout identity or amount to be changed directly', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $assignment = $auditorEvaluation->assignment;
    $assignment->update([
        'status' => 'completed',
        'completed_at' => now(),
        'due_at' => now()->addDay(),
    ]);
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $compensation = app(AuditorCompensationService::class)->assign($assignment, $admin, 15000, 'EUR');
    $compensation = app(AuditorCompensationService::class)->finalize($compensation);
    $payout = app(PayoutService::class)->create($admin, $compensation);

    expect(fn () => $payout->update(['amount_minor' => 1]))
        ->toThrow(DomainStateTransitionException::class);
});
