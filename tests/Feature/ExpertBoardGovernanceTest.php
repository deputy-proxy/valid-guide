<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorProfile;
use App\Models\ExpertBoardMembership;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertBoardGovernance;

function expertBoardAdmin(): User
{
    return User::factory()->create(['platform_role' => PlatformRole::Admin]);
}

function expertBoardProfile(array $overrides = []): AuditorProfile
{
    return AuditorProfile::create(array_merge([
        'auditor_id' => User::factory()->create()->id,
        'status' => AuditorProfileStatus::Approved,
        'methodology_literate' => true,
        'format_experience' => ['course'],
        'bio' => 'Experienced evaluator.',
        'credentials' => 'Relevant experience.',
        'approved_at' => now(),
    ], $overrides));
}

function clearAnnualConflict(User $auditor): void
{
    AuditorAnnualConflictDeclaration::create([
        'auditor_id' => $auditor->id,
        'year' => now()->year,
        'disclosure' => 'No current conflicts.',
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_by' => expertBoardAdmin()->id,
        'determined_at' => now(),
    ]);
}

it('allows only platform admins to govern the Expert Board', function () {
    $profile = expertBoardProfile();
    $membership = ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Pending,
        'applied_at' => now(),
    ]);
    $nonAdmin = User::factory()->create();

    expect(fn () => app(ExpertBoardGovernance::class)->approve($membership, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires an approved Auditor profile and current annual clearance before Board approval', function () {
    $admin = expertBoardAdmin();
    $profile = expertBoardProfile();
    $membership = ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Pending,
        'applied_at' => now(),
    ]);

    expect(fn () => app(ExpertBoardGovernance::class)->approve($membership, $admin))
        ->toThrow(DomainStateTransitionException::class);

    clearAnnualConflict($profile->auditor);

    expect(app(ExpertBoardGovernance::class)->approve($membership, $admin)->status)
        ->toBe(ExpertBoardMembershipStatus::Approved);
});

it('submits and re-submits Board applications without losing audit history', function () {
    $auditor = User::factory()->create();
    $profile = expertBoardProfile(['auditor_id' => $auditor->id]);
    $governance = app(ExpertBoardGovernance::class);

    $membership = $governance->apply($auditor);

    expect($membership->status)->toBe(ExpertBoardMembershipStatus::Pending)
        ->and($membership->applied_at)->not->toBeNull();

    $admin = expertBoardAdmin();
    $governance->reject($membership, $admin, 'More evidence is required.');
    $governance->apply($auditor);

    expect($membership->refresh()->status)->toBe(ExpertBoardMembershipStatus::Pending)
        ->and(AuditLog::query()->where('auditable_type', ExpertBoardMembership::class)->count())->toBe(3);
});

it('enforces controlled Board lifecycle transitions and records reasons', function () {
    $admin = expertBoardAdmin();
    $profile = expertBoardProfile();
    clearAnnualConflict($profile->auditor);
    $membership = ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Pending,
        'applied_at' => now(),
    ]);
    $governance = app(ExpertBoardGovernance::class);

    $governance->approve($membership, $admin);
    $governance->suspend($membership, $admin, 'Board review required.');
    $governance->reinstate($membership, $admin);
    $governance->remove($membership, $admin, 'Board membership concluded.');

    expect($membership->refresh()->status)->toBe(ExpertBoardMembershipStatus::Removed)
        ->and($membership->decision_reason)->toBe('Board membership concluded.')
        ->and(AuditLog::query()->where('auditable_type', ExpertBoardMembership::class)->count())->toBe(4);
});

it('rejects invalid transitions and blank reasons', function () {
    $admin = expertBoardAdmin();
    $profile = expertBoardProfile();
    $membership = ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Pending,
        'applied_at' => now(),
    ]);
    $governance = app(ExpertBoardGovernance::class);

    expect(fn () => $governance->reject($membership, $admin, '   '))
        ->toThrow(DomainStateTransitionException::class);

    $governance->reject($membership, $admin, 'Application rejected.');

    expect(fn () => $governance->approve($membership, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

it('prevents suspended members from being reinstated without current annual clearance', function () {
    $admin = expertBoardAdmin();
    $profile = expertBoardProfile();
    $membership = ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Suspended,
        'applied_at' => now(),
        'suspended_at' => now(),
    ]);

    expect(fn () => app(ExpertBoardGovernance::class)->reinstate($membership, $admin))
        ->toThrow(DomainStateTransitionException::class);
});
