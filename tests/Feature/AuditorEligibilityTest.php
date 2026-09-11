<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorProfile;
use App\Models\User;
use App\Services\AuditorEligibility;

function auditorEligibilityUser(AuditorProfileStatus $status = AuditorProfileStatus::Approved): User
{
    $user = User::factory()->create();
    $reviewer = User::factory()->create();

    AuditorProfile::create([
        'auditor_id' => $user->id,
        'status' => $status,
        'approved_by' => $status === AuditorProfileStatus::Approved ? $reviewer->id : null,
        'approved_at' => $status === AuditorProfileStatus::Approved ? now() : null,
    ]);

    return $user;
}

it('requires an approved profile and current annual clearance', function () {
    $auditor = auditorEligibilityUser();
    $eligibility = app(AuditorEligibility::class);

    expect($eligibility->canAccessAssignments($auditor))->toBeFalse();

    AuditorAnnualConflictDeclaration::create([
        'auditor_id' => $auditor->id,
        'year' => now()->year,
        'disclosure' => 'No known conflicts.',
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_by' => User::factory()->create()->id,
        'determined_at' => now(),
    ]);

    expect($eligibility->canAccessAssignments($auditor))->toBeTrue();
});

it('blocks non-approved auditors even with annual clearance', function () {
    $auditor = auditorEligibilityUser(AuditorProfileStatus::Pending);
    AuditorAnnualConflictDeclaration::create([
        'auditor_id' => $auditor->id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_by' => User::factory()->create()->id,
        'determined_at' => now(),
    ]);

    expect(app(AuditorEligibility::class)->canAccessAssignments($auditor))->toBeFalse();
});
