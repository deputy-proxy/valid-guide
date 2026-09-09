<?php

declare(strict_types=1);

use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\User;
use App\Services\AuditorAnnualConflictDeclarationService;
use App\Services\DomainStateTransitionException;

it('requires a platform admin to determine an annual conflict declaration', function () {
    $auditor = User::factory()->create();
    $declaration = app(AuditorAnnualConflictDeclarationService::class)->submit($auditor, 'No known conflicts.');
    $nonAdmin = User::factory()->create();

    expect(fn () => app(AuditorAnnualConflictDeclarationService::class)->determine($declaration, $nonAdmin, 'cleared'))
        ->toThrow(DomainStateTransitionException::class);
});

it('freezes an annual conflict declaration after determination', function () {
    $auditor = User::factory()->create();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $declaration = app(AuditorAnnualConflictDeclarationService::class)->submit($auditor);

    app(AuditorAnnualConflictDeclarationService::class)->determine($declaration, $admin, 'cleared');

    expect(fn () => $declaration->update(['disclosure' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});

it('recognizes only the current year cleared declaration', function () {
    $auditor = User::factory()->create();
    $service = app(AuditorAnnualConflictDeclarationService::class);

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $auditor->id,
        'year' => now()->year - 1,
        'outcome' => 'cleared',
        'submitted_at' => now()->subYear(),
        'determined_at' => now()->subYear(),
    ]);

    expect($service->isCurrentAndCleared($auditor))->toBeFalse();
});
