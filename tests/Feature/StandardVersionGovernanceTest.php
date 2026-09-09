<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\StandardVersionStatus;
use App\Models\Criterion;
use App\Models\CriterionGuidance;
use App\Models\EvaluationStandard;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\StandardVersionGovernance;

function standardVersionFixture(): array
{
    $standard = EvaluationStandard::create([
        'name' => 'Governance Standard',
        'slug' => 'governance-standard-'.uniqid(),
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'description' => 'Draft methodology.',
        'status' => StandardVersionStatus::Draft,
    ]);

    Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'GOV-BASE-01',
        'name' => 'Baseline criterion',
        'weight' => 100,
    ]);

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    return [$version, $admin];
}

it('schedules a draft version only with a future effective date and records approval', function () {
    [$version, $admin] = standardVersionFixture();
    $version->effective_at = now()->addDay();
    $version->save();

    $scheduled = app(StandardVersionGovernance::class)->schedule($version, $admin);

    expect($scheduled->status)->toBe(StandardVersionStatus::Scheduled)
        ->and($scheduled->approved_by)->toBe($admin->id)
        ->and($scheduled->approved_at)->not->toBeNull()
        ->and(DB::table('audit_logs')->where('event', 'standard_version.status_changed')->count())->toBe(1);
});

it('rejects scheduling without a future effective date', function () {
    [$version, $admin] = standardVersionFixture();

    expect(fn () => app(StandardVersionGovernance::class)->schedule($version, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

it('freezes standard version content once scheduled', function () {
    [$version, $admin] = standardVersionFixture();
    $version->effective_at = now()->addDay();
    $version->save();

    app(StandardVersionGovernance::class)->schedule($version, $admin);

    expect(fn () => $version->update(['description' => 'Changed after scheduling.']))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $version->delete())
        ->toThrow(DomainStateTransitionException::class);
});

it('freezes criteria and guidance once their standard version is scheduled', function () {
    [$version, $admin] = standardVersionFixture();

    $criterion = Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'GOV-01',
        'name' => 'Governance criterion',
        'weight' => 100,
    ]);

    $guidance = CriterionGuidance::create([
        'criterion_id' => $criterion->id,
        'title' => 'Guidance',
        'content' => 'Draft guidance.',
    ]);

    $version->effective_at = now()->addDay();
    $version->save();
    app(StandardVersionGovernance::class)->schedule($version, $admin);

    expect(fn () => $criterion->update(['name' => 'Changed criterion']))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $criterion->delete())
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $guidance->update(['content' => 'Changed guidance']))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $guidance->delete())
        ->toThrow(DomainStateTransitionException::class);
});

it('makes a scheduled version effective only on or after its effective date', function () {
    [$version, $admin] = standardVersionFixture();
    $version->effective_at = now()->addDay();
    $version->save();
    app(StandardVersionGovernance::class)->schedule($version, $admin);

    expect(fn () => app(StandardVersionGovernance::class)->makeEffective($version, $admin))
        ->toThrow(DomainStateTransitionException::class);

    $this->travel(2)->days();

    $effective = app(StandardVersionGovernance::class)->makeEffective($version->fresh(), $admin);

    expect($effective->status)->toBe(StandardVersionStatus::Effective);

    $this->travelBack();
});

it('does not allow overlapping effective versions of the same standard', function () {
    [$version, $admin] = standardVersionFixture();
    $version->effective_at = now()->addDay();
    $version->save();
    app(StandardVersionGovernance::class)->schedule($version, $admin);

    $this->travel(2)->days();
    app(StandardVersionGovernance::class)->makeEffective($version->fresh(), $admin);

    $second = StandardVersion::create([
        'evaluation_standard_id' => $version->evaluation_standard_id,
        'version' => '2.0',
        'effective_at' => now()->addDay(),
        'status' => StandardVersionStatus::Draft,
    ]);
    Criterion::create([
        'standard_version_id' => $second->id,
        'code' => 'GOV-BASE-02',
        'name' => 'Second baseline criterion',
        'weight' => 100,
    ]);

    app(StandardVersionGovernance::class)->schedule($second, $admin);

    expect(fn () => app(StandardVersionGovernance::class)->makeEffective($second->fresh(), $admin))
        ->toThrow(DomainStateTransitionException::class);

    $this->travelBack();
});

it('requires a platform administrator for standard governance', function () {
    [$version] = standardVersionFixture();
    $version->effective_at = now()->addDay();
    $version->save();
    $nonAdmin = User::factory()->create();

    expect(fn () => app(StandardVersionGovernance::class)->schedule($version, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});
