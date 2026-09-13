<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\CommunityContributionStatus;
use App\Enums\CommunityReportReason;
use App\Enums\CommunityReportStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Filament\Resources\CommunityContributions\CommunityContributionResource;
use App\Filament\Resources\CommunityReports\CommunityReportResource;
use App\Models\AuditLog;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorProfile;
use App\Models\CommunityContribution;
use App\Models\CommunityReport;
use App\Models\ExpertBoardMembership;
use App\Models\ExpertPublicProfile;
use App\Models\User;
use App\Services\CommunityContributionGovernance;
use App\Services\DomainStateTransitionException;

function communityExpert(): User
{
    $expert = User::factory()->create(['email_verified_at' => now()]);
    $profile = AuditorProfile::create([
        'auditor_id' => $expert->id,
        'status' => AuditorProfileStatus::Approved,
        'methodology_literate' => true,
        'format_experience' => ['course'],
        'bio' => 'Public expert bio.',
        'credentials' => 'Public expert credentials.',
    ]);
    ExpertBoardMembership::create([
        'auditor_profile_id' => $profile->id,
        'status' => ExpertBoardMembershipStatus::Approved,
        'approved_at' => now(),
    ]);
    AuditorAnnualConflictDeclaration::create([
        'auditor_id' => $expert->id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_at' => now(),
    ]);

    ExpertPublicProfile::create([
        'auditor_profile_id' => $profile->id,
        'slug' => 'expert-'.$expert->id,
        'display_name' => 'Public Expert '.$expert->id,
        'bio' => 'Public profile biography.',
        'credentials' => 'Public credentials.',
        'expertise_areas' => ['instructional_design'],
        'product_types' => ['course'],
        'status' => 'published',
        'published_at' => now(),
    ]);

    return $expert->refresh();
}

function communityAdmin(): User
{
    return User::factory()->create(['platform_role' => 'admin', 'email_verified_at' => now()]);
}

function communityContribution(User $expert, CommunityContributionStatus $status = CommunityContributionStatus::Draft): CommunityContribution
{
    return CommunityContribution::create([
        'auditor_profile_id' => $expert->auditorProfile->id,
        'slug' => 'contribution-'.$expert->id.'-'.fake()->unique()->numerify('###'),
        'title' => 'A useful expert contribution',
        'body' => 'Practical knowledge shared independently of Validation.',
        'status' => $status,
        'published_at' => $status === CommunityContributionStatus::Published ? now() : null,
    ]);
}

it('allows an eligible Expert to create and submit a contribution', function () {
    $expert = communityExpert();
    $service = app(CommunityContributionGovernance::class);

    $contribution = $service->createDraft($expert, 'Writing better course objectives', 'Use observable outcomes and test them against the learning activity.');
    $service->submit($contribution, $expert);

    expect($contribution->refresh()->status)->toBe(CommunityContributionStatus::PendingReview)
        ->and($contribution->auditor_profile_id)->toBe($expert->auditorProfile->id);
});

it('rejects community participation without current Expert eligibility', function () {
    $user = User::factory()->create();

    expect(fn () => app(CommunityContributionGovernance::class)->createDraft($user, 'Title', 'Body'))
        ->toThrow(DomainStateTransitionException::class);
});

it('allows only the author to edit a draft or rejected contribution', function () {
    $expert = communityExpert();
    $other = communityExpert();
    $contribution = communityContribution($expert);
    $service = app(CommunityContributionGovernance::class);

    $updated = $service->updateDraft($contribution, $expert, 'Updated title', 'Updated body.');
    expect($updated->title)->toBe('Updated title');

    expect(fn () => $service->updateDraft($updated, $other, 'Nope', 'Nope'))
        ->toThrow(DomainStateTransitionException::class);
});

it('allows an administrator to moderate and records immutable audit events', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $contribution = communityContribution($expert);
    $service = app(CommunityContributionGovernance::class);

    $service->submit($contribution, $expert);
    $service->publish($contribution, $admin);

    expect($contribution->refresh()->status)->toBe(CommunityContributionStatus::Published)
        ->and($contribution->published_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event', 'community_contribution.published')->count())->toBe(1);
});

it('blocks non-administrators from moderation and requires moderation reasons', function () {
    $expert = communityExpert();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    $nonAdmin = User::factory()->create();
    $service = app(CommunityContributionGovernance::class);

    expect(fn () => $service->publish($contribution, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);

    $admin = communityAdmin();
    expect(fn () => $service->reject($contribution, $admin, '   '))
        ->toThrow(DomainStateTransitionException::class);
});

it('keeps rejected content editable and unpublished', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    $service = app(CommunityContributionGovernance::class);

    $service->reject($contribution, $admin, 'Please clarify the source of this claim.');
    $updated = $service->updateDraft($contribution, $expert, 'Clarified contribution', 'Clarified body.');

    expect($updated->status)->toBe(CommunityContributionStatus::Draft)
        ->and($updated->moderation_reason)->toBeNull()
        ->and($updated->published_at)->toBeNull();
});

it('prevents public access to unpublished and moderated content', function () {
    $expert = communityExpert();
    $draft = communityContribution($expert);
    $hidden = communityContribution($expert, CommunityContributionStatus::Hidden);
    $removed = communityContribution($expert, CommunityContributionStatus::Removed);

    expect($this->get(route('public.community.show', $draft->slug))->status())->toBe(404)
        ->and($this->get(route('public.community.show', $hidden->slug))->status())->toBe(404)
        ->and($this->get(route('public.community.show', $removed->slug))->status())->toBe(404);
});

it('publishes only through the public directory boundary', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    app(CommunityContributionGovernance::class)->publish($contribution, $admin);

    $response = $this->get(route('public.community.show', $contribution->slug));

    $response->assertOk()->assertSee($contribution->title)->assertSee('independent of Validation');
});

it('does not expose private report data on public community pages', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    app(CommunityContributionGovernance::class)->publish($contribution, $admin);
    $reporter = User::factory()->create(['email_verified_at' => now()]);
    $report = app(CommunityContributionGovernance::class)->report($contribution, $reporter, CommunityReportReason::Privacy, 'Private moderation evidence.');

    $response = $this->get(route('public.community.show', $contribution->slug));

    $response->assertOk()->assertDontSee($report->details)->assertDontSee('CommunityReport');
});

it('allows users to report published content and prevents duplicate open reports', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $reporter = User::factory()->create();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    app(CommunityContributionGovernance::class)->publish($contribution, $admin);
    $service = app(CommunityContributionGovernance::class);

    $report = $service->report($contribution, $reporter, CommunityReportReason::Inappropriate, 'Please review this content.');

    expect($report->status)->toBe(CommunityReportStatus::Open);
    expect(fn () => $service->report($contribution, $reporter, CommunityReportReason::Other))
        ->toThrow(DomainStateTransitionException::class);
});

it('prevents authors from reporting their own contributions', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    app(CommunityContributionGovernance::class)->publish($contribution, $admin);

    expect(fn () => app(CommunityContributionGovernance::class)->report($contribution, $expert, CommunityReportReason::Other))
        ->toThrow(DomainStateTransitionException::class);
});

it('allows administrators to resolve or dismiss reports with a reason', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $reporter = User::factory()->create();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    app(CommunityContributionGovernance::class)->publish($contribution, $admin);
    $service = app(CommunityContributionGovernance::class);

    $resolved = $service->report($contribution, $reporter, CommunityReportReason::Other);
    $service->resolveReport($resolved, $admin, 'Reviewed and resolved.');

    $dismissed = $service->report($contribution, $reporter, CommunityReportReason::Privacy);
    $service->resolveReport($dismissed, $admin, 'No policy violation found.', true);

    expect($resolved->refresh()->status)->toBe(CommunityReportStatus::Resolved)
        ->and($dismissed->refresh()->status)->toBe(CommunityReportStatus::Dismissed)
        ->and(CommunityReport::query()->where('resolved_by', $admin->id)->count())->toBe(2);
});

it('keeps community moderation independent from Validation history', function () {
    $expert = communityExpert();
    $admin = communityAdmin();
    $contribution = communityContribution($expert, CommunityContributionStatus::PendingReview);
    $service = app(CommunityContributionGovernance::class);

    $service->publish($contribution, $admin);
    $service->remove($contribution, $admin, 'Policy violation.');

    expect(AuditLog::query()->where('auditable_type', CommunityContribution::class)->count())->toBeGreaterThanOrEqual(2);
    expect($contribution->refresh()->status)->toBe(CommunityContributionStatus::Removed);
});

it('allows only administrators to access community moderation resources', function () {
    $admin = communityAdmin();
    $nonAdmin = User::factory()->create();

    $this->actingAs($nonAdmin);
    expect(CommunityContributionResource::canAccess())->toBeFalse();

    $this->actingAs($admin);
    expect(CommunityContributionResource::canAccess())->toBeTrue();
    expect(CommunityReportResource::canAccess())->toBeTrue();
});

it('renders the public community empty state and the Expert community page', function () {
    $this->get(route('public.community'))->assertOk()->assertSee('No community contributions');

    $expert = communityExpert();
    $this->actingAs($expert)->get(route('expert.community'))->assertOk()->assertSee('Share knowledge');
});
