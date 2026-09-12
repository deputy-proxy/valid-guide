<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertOpportunityStatus;
use App\Enums\ExpertOpportunityType;
use App\Enums\PlatformRole;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorProfile;
use App\Models\ExpertBoardMembership;
use App\Models\ExpertOpportunity;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertOpportunityGovernance;
use App\Services\ExpertOpportunityParticipationService;

function opportunityAdmin(): User
{
    return User::factory()->create(['platform_role' => PlatformRole::Admin]);
}

function opportunityExpert(): User
{
    $expert = User::factory()->create();
    $profile = AuditorProfile::create([
        'auditor_id' => $expert->id,
        'status' => AuditorProfileStatus::Approved,
        'methodology_literate' => true,
        'format_experience' => ['course'],
        'bio' => 'Expert',
        'credentials' => 'Credentials',
        'approved_at' => now(),
    ]);
    ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Approved,
        'applied_at' => now(),
        'approved_at' => now(),
    ]);
    AuditorAnnualConflictDeclaration::create([
        'auditor_id' => $expert->id,
        'year' => now()->year,
        'disclosure' => 'Clear.',
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_by' => opportunityAdmin()->id,
        'determined_at' => now(),
    ]);

    return $expert;
}

function opportunityDraft(User $admin): ExpertOpportunity
{
    return ExpertOpportunity::create([
        'title' => 'Review opportunity',
        'description' => 'Review a product.',
        'type' => ExpertOpportunityType::Review,
        'expertise_areas' => [],
        'product_types' => ['course'],
        'workload' => '4 hours',
        'application_deadline' => now()->addWeek(),
        'eligibility_constraints' => ['methodology_literate' => true],
        'status' => ExpertOpportunityStatus::Draft,
        'created_by' => $admin->id,
    ]);
}

it('controls opportunity lifecycle', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    $service = app(ExpertOpportunityGovernance::class);

    expect($service->publish($opportunity, $admin)->status)->toBe(ExpertOpportunityStatus::Published);
    expect($service->close($opportunity, $admin)->status)->toBe(ExpertOpportunityStatus::Closed);
    expect($service->complete($opportunity, $admin)->status)->toBe(ExpertOpportunityStatus::Completed);
});

it('rejects invalid transitions', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);

    expect(fn () => app(ExpertOpportunityGovernance::class)->complete($opportunity, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires eligible board membership', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    app(ExpertOpportunityGovernance::class)->publish($opportunity, $admin);
    $expert = User::factory()->create();

    expect(fn () => app(ExpertOpportunityParticipationService::class)->apply($opportunity, $expert, 'Clear.'))
        ->toThrow(DomainStateTransitionException::class);
});

it('protects published opportunity details', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    app(ExpertOpportunityGovernance::class)->publish($opportunity, $admin);

    expect(fn () => $opportunity->update(['title' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});
