<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AuditorCompensationService;
use App\Services\DomainStateTransitionException;
use App\Services\PayoutService;

it('creates and pays a payout only from payable compensation', function () {
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
    $payout = app(PayoutService::class)->markPaid($payout, $admin, 'PAY-001');

    expect($payout->status)->toBe('paid')
        ->and($compensation->fresh()->status)->toBe('paid');
});

it('rejects payouts containing non-payable compensation', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $compensation = app(AuditorCompensationService::class)->assign($auditorEvaluation->assignment, $admin, 15000, 'EUR');

    expect(fn () => app(PayoutService::class)->create($admin, $compensation))
        ->toThrow(DomainStateTransitionException::class);
});
