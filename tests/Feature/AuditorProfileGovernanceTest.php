<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\PlatformRole;
use App\Models\AuditorCompetency;
use App\Models\AuditorProfile;
use App\Models\AuditorProfileReview;
use App\Models\User;
use App\Services\AuditorProfileGovernance;
use App\Services\DomainStateTransitionException;

function auditorProfileGovernanceAdmin(): User
{
    return User::factory()->create(['platform_role' => PlatformRole::Admin]);
}

function auditorProfileGovernanceProfile(array $overrides = []): AuditorProfile
{
    return AuditorProfile::create(array_merge([
        'auditor_id' => User::factory()->create()->id,
        'status' => AuditorProfileStatus::Pending,
        'methodology_literate' => true,
        'format_experience' => ['course'],
        'bio' => 'Experienced evaluator.',
        'credentials' => 'Relevant experience.',
    ], $overrides));
}

it('allows only platform admins to govern auditor profiles', function () {
    $profile = auditorProfileGovernanceProfile();
    $nonAdmin = User::factory()->create();

    expect(fn () => app(AuditorProfileGovernance::class)->approve($profile, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});

it('approves a pending profile and records immutable review provenance', function () {
    $admin = auditorProfileGovernanceAdmin();
    $profile = auditorProfileGovernanceProfile();

    $approved = app(AuditorProfileGovernance::class)->approve($profile, $admin);

    expect($approved->status)->toBe(AuditorProfileStatus::Approved)
        ->and($approved->approved_by)->toBe($admin->id)
        ->and($approved->approved_at)->not->toBeNull()
        ->and(AuditorProfileReview::where('auditor_profile_id', $profile->id)->where('action', 'approved')->count())->toBe(1);
});

it('supports suspension and reapproval without erasing prior review history', function () {
    $admin = auditorProfileGovernanceAdmin();
    $profile = auditorProfileGovernanceProfile(['status' => AuditorProfileStatus::Approved, 'approved_by' => $admin->id, 'approved_at' => now()]);

    app(AuditorProfileGovernance::class)->suspend($profile, $admin, 'Material competency evidence needs re-review.');
    app(AuditorProfileGovernance::class)->approve($profile, $admin);

    expect($profile->refresh()->status)->toBe(AuditorProfileStatus::Approved)
        ->and(AuditorProfileReview::where('auditor_profile_id', $profile->id)->count())->toBe(2);
});

it('requires reasons for rejection and suspension', function () {
    $admin = auditorProfileGovernanceAdmin();
    $pending = auditorProfileGovernanceProfile();

    expect(fn () => app(AuditorProfileGovernance::class)->reject($pending, $admin, '   '))
        ->toThrow(DomainStateTransitionException::class);

    $approved = auditorProfileGovernanceProfile(['status' => AuditorProfileStatus::Approved, 'approved_by' => $admin->id, 'approved_at' => now()]);

    expect(fn () => app(AuditorProfileGovernance::class)->suspend($approved, $admin, '   '))
        ->toThrow(DomainStateTransitionException::class);
});

it('does not allow invalid profile status transitions', function () {
    $admin = auditorProfileGovernanceAdmin();
    $rejected = auditorProfileGovernanceProfile(['status' => AuditorProfileStatus::Rejected]);

    expect(fn () => app(AuditorProfileGovernance::class)->suspend($rejected, $admin, 'Invalid transition.'))
        ->toThrow(DomainStateTransitionException::class);
});

it('verifies a competency once and records who verified it', function () {
    $admin = auditorProfileGovernanceAdmin();
    $profile = auditorProfileGovernanceProfile();
    $competency = AuditorCompetency::create([
        'auditor_profile_id' => $profile->id,
        'topic' => 'Instructional Design',
        'experience_type' => 'teaching',
        'years_experience' => 8,
        'evidence' => 'Documented professional experience.',
    ]);

    $verified = app(AuditorProfileGovernance::class)->verifyCompetency($competency, $admin);

    expect($verified->verified_at)->not->toBeNull()
        ->and($verified->verified_by)->toBe($admin->id)
        ->and(fn () => app(AuditorProfileGovernance::class)->verifyCompetency($verified, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects competency verification without required evidence fields', function () {
    $admin = auditorProfileGovernanceAdmin();
    $profile = auditorProfileGovernanceProfile();
    $competency = AuditorCompetency::create([
        'auditor_profile_id' => $profile->id,
        'topic' => '',
        'experience_type' => 'teaching',
    ]);

    expect(fn () => app(AuditorProfileGovernance::class)->verifyCompetency($competency, $admin))
        ->toThrow(DomainStateTransitionException::class);
});
