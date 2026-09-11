<?php

declare(strict_types=1);

use App\Enums\ImprovementOpportunityStatus;
use App\Models\Finding;
use App\Services\CreatorActionPlanner;
use App\Services\CreatorDashboard;
use App\Services\ImprovementOpportunityWorkflow;
use App\Services\RoleActionQueue;

it('surfaces active opportunities in the creator dashboard and action queue', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    app(CreatorActionPlanner::class)->generate($evaluation, $organization, $creator);
    $opportunity = $evaluation->improvementOpportunities()->where('finding_id', $finding->id)->firstOrFail();

    $dashboard = app(CreatorDashboard::class)->forOrganization($creator, $organization->id);
    $queue = app(RoleActionQueue::class)->forCreator($creator);

    expect($dashboard['improvement_opportunities'])->toHaveCount(1)
        ->and($dashboard['improvement_opportunities'][0]['id'])->toBe($opportunity->id)
        ->and($queue->contains(fn ($item): bool => $item->targetType === 'improvement_opportunity' && $item->targetId === $opportunity->id))->toBeTrue();
});

it('removes completed opportunities from the active creator queue', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $opportunity = app(ImprovementOpportunityWorkflow::class)->createFromFinding($finding, $organization, $creator);
    app(ImprovementOpportunityWorkflow::class)->complete($opportunity, $creator, 'Supporting release evidence.');

    $queue = app(RoleActionQueue::class)->forCreator($creator);

    expect($opportunity->fresh()->status)->toBe(ImprovementOpportunityStatus::Completed)
        ->and($queue->contains(fn ($item): bool => $item->targetType === 'improvement_opportunity' && $item->targetId === $opportunity->id))->toBeFalse();
});
