<?php

declare(strict_types=1);

use App\Enums\ExpertOpportunityParticipationStatus;
use App\Services\DomainStateTransitionException;
use App\Services\ExpertOpportunityParticipationService;

it('requires conflict clearance before selection', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    app(\App\Services\ExpertOpportunityGovernance::class)->publish($opportunity, $admin);
    $expert = opportunityExpert();
    $service = app(ExpertOpportunityParticipationService::class);
    $participation = $service->apply($opportunity, $expert, 'Clear.');

    expect(fn () => $service->select($participation, $admin))->toThrow(DomainStateTransitionException::class);
    $service->determineConflict($participation, $admin, 'cleared');
    expect($service->select($participation, $admin)->status)->toBe(ExpertOpportunityParticipationStatus::Selected);
});

it('supports acceptance and completion', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    app(\App\Services\ExpertOpportunityGovernance::class)->publish($opportunity, $admin);
    $expert = opportunityExpert();
    $service = app(ExpertOpportunityParticipationService::class);
    $participation = $service->apply($opportunity, $expert, 'Clear.');
    $service->determineConflict($participation, $admin, 'cleared');
    $service->select($participation, $admin);
    $service->accept($participation, $expert);

    expect($service->complete($participation, $admin)->status)->toBe(ExpertOpportunityParticipationStatus::Completed);
});

it('prevents changing determined conflicts', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    app(\App\Services\ExpertOpportunityGovernance::class)->publish($opportunity, $admin);
    $participation = app(ExpertOpportunityParticipationService::class)->apply($opportunity, opportunityExpert(), 'Clear.');
    app(ExpertOpportunityParticipationService::class)->determineConflict($participation, $admin, 'cleared');

    expect(fn () => $participation->update(['conflict_outcome' => 'conflicted']))->toThrow(DomainStateTransitionException::class);
});

it('prevents another expert from withdrawing an application', function () {
    $admin = opportunityAdmin();
    $opportunity = opportunityDraft($admin);
    app(\App\Services\ExpertOpportunityGovernance::class)->publish($opportunity, $admin);
    $expert = opportunityExpert();
    $other = opportunityExpert();
    $participation = app(ExpertOpportunityParticipationService::class)->apply($opportunity, $expert, 'Clear.');

    expect(fn () => app(ExpertOpportunityParticipationService::class)->withdraw($participation, $other))->toThrow(DomainStateTransitionException::class);
});
