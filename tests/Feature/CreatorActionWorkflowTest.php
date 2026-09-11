<?php

declare(strict_types=1);

use App\Enums\CreatorActionPriority;
use App\Enums\CreatorActionStatus;
use App\Enums\PlatformRole;
use App\Livewire\Creator\Reports\ShowReport;
use App\Models\CreatorAction;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\User;
use App\Services\CreatorActionPlanner;
use App\Services\CreatorActionWorkflow;
use App\Services\CreatorReportAccess;
use App\Services\DomainStateTransitionException;
use App\Services\ReportVersioning;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function creatorActionEvaluationFixture(): array
{
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;

    DB::table('evaluations')
        ->where('id', $evaluation->id)
        ->update([
            'status' => 'completed',
            'decision' => 'validated',
            'overall_score' => 80,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

    $evaluation->refresh();
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    $organization = $evaluation->request->organization;
    $creator = User::factory()->create();
    $organization->users()->attach($creator, ['role' => 'editor']);

    return [$evaluation, $organization, $creator];
}

it('creates creator actions with tenant and source integrity', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
        CreatorActionPriority::High,
        $finding,
    );

    expect($action->organization_id)->toBe($organization->id)
        ->and($action->evaluation_id)->toBe($evaluation->id)
        ->and($action->finding_id)->toBe($finding->id)
        ->and($action->status)->toBe(CreatorActionStatus::Pending)
        ->and($action->priority)->toBe(CreatorActionPriority::High);
});

it('generates actions from creator-visible actionable findings idempotently', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();

    Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'strength',
        'severity' => 'low',
        'title' => 'Strong methodology',
        'description' => 'The methodology is clear.',
    ]);

    $planner = app(CreatorActionPlanner::class);
    $first = $planner->generate($evaluation, $organization, $creator);
    $second = $planner->generate($evaluation->refresh(), $organization, $creator);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and(CreatorAction::query()->where('evaluation_id', $evaluation->id)->count())->toBe(1)
        ->and($first->first()->priority)->toBe(CreatorActionPriority::High);
});

it('enforces the creator action lifecycle', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();
    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
    );

    app(CreatorActionWorkflow::class)->transition($action, $creator, CreatorActionStatus::InProgress);
    $action->refresh();

    expect($action->status)->toBe(CreatorActionStatus::InProgress)
        ->and($action->completed_at)->toBeNull();

    app(CreatorActionWorkflow::class)->transition($action, $creator, CreatorActionStatus::Completed);
    $action->refresh();

    expect($action->status)->toBe(CreatorActionStatus::Completed)
        ->and($action->completed_at)->not->toBeNull();

    expect(fn () => app(CreatorActionWorkflow::class)->transition($action, $creator, CreatorActionStatus::Pending))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects cross-tenant action management and assignment', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();
    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
    );

    $otherOrganization = Organization::query()->create([
        'name' => 'Other Organization',
        'slug' => 'other-organization-'.uniqid(),
        'status' => 'active',
    ]);
    $otherUser = User::factory()->create();
    $otherOrganization->users()->attach($otherUser, ['role' => 'editor']);

    expect(fn () => app(CreatorActionWorkflow::class)->transition($action, $otherUser, CreatorActionStatus::InProgress))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => app(CreatorActionWorkflow::class)->assign($action, $creator, $otherUser))
        ->toThrow(DomainStateTransitionException::class);
});

it('exposes only tenant-authorized actions through creator report access', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();
    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
    );

    $data = app(CreatorReportAccess::class)->show($creator, $evaluation->id);

    expect($data['actions']['items'])->toHaveCount(1)
        ->and($data['actions']['items'][0]['id'])->toBe($action->id)
        ->and($data['actions']['items'][0]['status'])->toBe('pending')
        ->and($data['actions']['summary']['pending'])->toBe(1);
});

it('renders and updates the creator action plan through Livewire', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();
    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
    );

    Livewire::actingAs($creator)
        ->test(ShowReport::class, ['evaluationId' => $evaluation->id])
        ->assertStatus(200)
        ->assertSee('Creator Action Plan')
        ->assertSee('Improve onboarding')
        ->call('transitionAction', $action->id, 'in_progress')
        ->assertSee('In Progress');
});

it('blocks creator action Livewire mutations for another tenant', function () {
    [$evaluation, $organization, $creator] = creatorActionEvaluationFixture();
    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
    );

    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test(ShowReport::class, ['evaluationId' => $evaluation->id])
        ->assertStatus(403);

    expect($action->fresh()->status)->toBe(CreatorActionStatus::Pending);
});
