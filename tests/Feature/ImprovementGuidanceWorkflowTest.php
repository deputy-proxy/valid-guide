<?php

declare(strict_types=1);

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementGuidanceCategory;
use App\Enums\ImprovementGuidanceStatus;
use App\Enums\PlatformRole;
use App\Models\CreatorAction;
use App\Models\Finding;
use App\Models\ImprovementGuidance;
use App\Models\Organization;
use App\Models\User;
use App\Services\CreatorActionWorkflow;
use App\Services\CreatorImprovementGuidanceAccess;
use App\Services\DomainStateTransitionException;
use App\Services\ImprovementGuidancePlanner;
use App\Services\ImprovementGuidanceWorkflow;
use App\Services\ReportVersioning;
use Illuminate\Support\Facades\DB;

function improvementGuidanceEvaluationFixture(): array
{
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;

    DB::table('evaluations')->where('id', $evaluation->id)->update([
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

it('creates structured guidance with immutable evaluation provenance', function () {
    [$evaluation, $organization, $creator] = improvementGuidanceEvaluationFixture();
    $finding = Finding::query()->create([
        'evaluation_id' => $evaluation->id,
        'type' => 'weakness',
        'severity' => 'high',
        'title' => 'Improve onboarding',
        'description' => 'Clarify the first-use experience.',
    ]);

    $guidance = app(ImprovementGuidanceWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
        'The finding indicates friction in the first-use experience.',
        ImprovementGuidanceCategory::Content,
        CreatorActionPriority::High,
        $finding,
        ['audience' => ['new learners']],
        true,
    );

    expect($guidance->status)->toBe(ImprovementGuidanceStatus::Draft)
        ->and($guidance->evaluation_id)->toBe($evaluation->id)
        ->and($guidance->organization_id)->toBe($organization->id)
        ->and($guidance->finding_id)->toBe($finding->id)
        ->and($guidance->priority)->toBe(CreatorActionPriority::High)
        ->and($guidance->applicability)->toBe(['audience' => ['new learners']]);

    expect(fn () => $guidance->update(['title' => 'Changed']))
        ->toThrow(DomainStateTransitionException::class);
});

it('publishes guidance and exposes only creator-visible guidance', function () {
    [$evaluation, $organization, $creator] = improvementGuidanceEvaluationFixture();
    $guidance = app(ImprovementGuidanceWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
        'The first-use experience needs improvement.',
        ImprovementGuidanceCategory::Content,
    );

    $access = app(CreatorImprovementGuidanceAccess::class);

    expect($access->show($creator, $evaluation->id)['items'])->toHaveCount(0);

    app(ImprovementGuidanceWorkflow::class)->publish($guidance, $creator);

    expect($access->show($creator, $evaluation->id)['items'])->toHaveCount(1);
});

it('generates guidance and a creator action idempotently from actionable findings', function () {
    [$evaluation, $organization, $creator] = improvementGuidanceEvaluationFixture();
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

    $planner = app(ImprovementGuidancePlanner::class);
    $first = $planner->generate($evaluation, $organization, $creator);
    $second = $planner->generate($evaluation->refresh(), $organization, $creator);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and(ImprovementGuidance::query()->where('evaluation_id', $evaluation->id)->count())->toBe(1)
        ->and(CreatorAction::query()->where('evaluation_id', $evaluation->id)->count())->toBe(1);
});

it('rejects cross-tenant guidance creation', function () {
    [$evaluation] = improvementGuidanceEvaluationFixture();
    $otherOrganization = Organization::query()->create([
        'name' => 'Other Organization',
        'slug' => 'other-organization-'.uniqid(),
        'status' => 'active',
    ]);
    $otherUser = User::factory()->create();
    $otherOrganization->users()->attach($otherUser, ['role' => 'editor']);

    expect(fn () => app(ImprovementGuidanceWorkflow::class)->create(
        $evaluation,
        $otherOrganization,
        $otherUser,
        'Invalid guidance',
        'Invalid guidance.',
        'Invalid provenance.',
        ImprovementGuidanceCategory::Other,
    ))->toThrow(DomainStateTransitionException::class);
});

it('keeps existing creator action workflow compatible with guidance', function () {
    [$evaluation, $organization, $creator] = improvementGuidanceEvaluationFixture();
    $guidance = app(ImprovementGuidanceWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
        'The first-use experience needs improvement.',
        ImprovementGuidanceCategory::Content,
    );

    $action = app(CreatorActionWorkflow::class)->create(
        $evaluation,
        $organization,
        $creator,
        'Improve onboarding',
        'Clarify the first-use experience.',
        CreatorActionPriority::High,
        null,
        $guidance,
    );

    expect($action->improvement_guidance_id)->toBe($guidance->id)
        ->and($action->improvementGuidance->id)->toBe($guidance->id);
});
