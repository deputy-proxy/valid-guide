<?php

declare(strict_types=1);

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementOpportunityStatus;
use App\Models\Finding;
use App\Models\ImprovementOpportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ImprovementOpportunityWorkflow;

it('creates and tracks an improvement opportunity from an authoritative finding', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $opportunity = app(ImprovementOpportunityWorkflow::class)->createFromFinding(
        $finding,
        $organization,
        $creator,
    );

    expect($opportunity->organization_id)->toBe($organization->id)
        ->and($opportunity->evaluation_id)->toBe($evaluation->id)
        ->and($opportunity->finding_id)->toBe($finding->id)
        ->and($opportunity->product_release_id)->toBe($evaluation->product_release_id)
        ->and($opportunity->priority)->toBe(CreatorActionPriority::High)
        ->and($opportunity->status)->toBe(ImprovementOpportunityStatus::Open);
});

it('generates opportunities idempotently and links the existing creator action', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $planner = app(\App\Services\CreatorActionPlanner::class);
    $first = $planner->generate($evaluation, $organization, $creator);
    $second = $planner->generate($evaluation->refresh(), $organization, $creator);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and(ImprovementOpportunity::query()->where('finding_id', $finding->id)->count())->toBe(1)
        ->and($first->first()->improvement_opportunity_id)->not->toBeNull()
        ->and($second->first()->improvement_opportunity_id)->toBe($first->first()->improvement_opportunity_id);
});

it('requires evidence before completion and preserves source provenance', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'medium',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $opportunity = app(ImprovementOpportunityWorkflow::class)->createFromFinding($finding, $organization, $creator);

    app(ImprovementOpportunityWorkflow::class)->transition($opportunity, $creator, ImprovementOpportunityStatus::InProgress);

    expect(fn () => app(ImprovementOpportunityWorkflow::class)->complete($opportunity, $creator, ''))
        ->toThrow(DomainStateTransitionException::class);

    app(ImprovementOpportunityWorkflow::class)->complete(
        $opportunity->refresh(),
        $creator,
        'Updated onboarding flow and supporting release evidence.',
    );

    $opportunity->refresh();

    expect($opportunity->status)->toBe(ImprovementOpportunityStatus::Completed)
        ->and($opportunity->completion_evidence)->toBe('Updated onboarding flow and supporting release evidence.')
        ->and($opportunity->completed_at)->not->toBeNull();

    expect(fn () => $opportunity->update(['finding_id' => null]))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects cross-tenant opportunity management', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $opportunity = app(ImprovementOpportunityWorkflow::class)->createFromFinding($finding, $organization, $creator);
    $otherOrganization = Organization::query()->create([
        'name' => 'Other Organization',
        'slug' => 'other-organization-'.uniqid(),
        'status' => 'active',
    ]);
    $otherUser = User::factory()->create();
    $otherOrganization->users()->attach($otherUser, ['role' => 'editor']);

    expect(fn () => app(ImprovementOpportunityWorkflow::class)->transition(
        $opportunity,
        $otherUser,
        ImprovementOpportunityStatus::InProgress,
    ))->toThrow(DomainStateTransitionException::class);

    expect($opportunity->fresh()->status)->toBe(ImprovementOpportunityStatus::Open);
});
