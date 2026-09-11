<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Livewire\Creator\Reports\ShowReport;
use App\Models\ReportVersion;
use App\Models\User;
use App\Models\Validation;
use App\Models\ValidationBadge;
use App\Services\CreatorReportAccess;
use App\Services\DomainStateTransitionException;
use App\Services\ReportVersioning;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function creatorReportFixture(): array
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

    return [$evaluation, $admin];
}

it('returns only creator-permitted report and validation data', function () {
    [$evaluation, $admin] = creatorReportFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    $organization = $evaluation->request->organization;
    $creator = User::factory()->create();
    $organization->users()->attach($creator, ['role' => 'editor']);

    $validation = Validation::query()->create([
        'product_release_id' => $evaluation->product_release_id,
        'evaluation_id' => $evaluation->id,
        'verification_identifier' => 'creator-test-'.$evaluation->id,
        'issued_at' => now(),
        'status' => 'active',
    ]);
    ValidationBadge::query()->create([
        'validation_id' => $validation->id,
        'verification_identifier' => $validation->verification_identifier,
        'status' => 'active',
        'issued_at' => now(),
        'embed_version' => '1',
    ]);

    $data = app(CreatorReportAccess::class)->show($creator, $evaluation->id);

    expect($data['evaluation']['decision'])->toBe('validated')
        ->and($data['evaluation']['overall_score'])->toBe('80.00')
        ->and($data)->toHaveKeys(['criteria', 'findings', 'strengths', 'weaknesses', 'report', 'validation'])
        ->and($data['report']['current']['version_number'])->toBe(1)
        ->and($data['validation']['status'])->toBe('active')
        ->and($data['validation']['badge']['embed_version'])->toBe('1')
        ->and($data)->not->toHaveKey('auditor_evaluations')
        ->and($data)->not->toHaveKey('assignments')
        ->and($data)->not->toHaveKey('evidence')
        ->and($data['evaluation'])->not->toHaveKey('decision_rationale');
});

it('exposes report versions as read-only historical snapshots', function () {
    [$evaluation, $admin] = creatorReportFixture();
    $first = app(ReportVersioning::class)->createInitial($evaluation, $admin);
    $second = app(ReportVersioning::class)->createRevision(
        $first->report,
        $admin,
        ['sections' => ['summary' => 'Corrected report.']],
        'Corrected report.',
        'Material factual correction.',
    );

    $organization = $evaluation->request->organization;
    $creator = User::factory()->create();
    $organization->users()->attach($creator, ['role' => 'owner']);

    $data = app(CreatorReportAccess::class)->show($creator, $evaluation->id);

    expect($data['report']['current_version_id'])->toBe($second->id)
        ->and($data['report']['versions'])->toHaveCount(2)
        ->and(collect($data['report']['versions'])->firstWhere('id', $first->id)['abstract'])
        ->not->toBe('Corrected report.');

    $version = ReportVersion::query()->findOrFail($first->id);
    $version->abstract = 'tampered';

    expect(fn () => $version->save())->toThrow(DomainStateTransitionException::class);
});

it('rejects users outside the evaluation organization', function () {
    [$evaluation, $admin] = creatorReportFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    expect(fn () => app(CreatorReportAccess::class)->show(User::factory()->create(), $evaluation->id))
        ->toThrow(AuthorizationException::class);
});

it('rejects non-content organization roles', function () {
    [$evaluation, $admin] = creatorReportFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    $organization = $evaluation->request->organization;
    $billing = User::factory()->create();
    $organization->users()->attach($billing, ['role' => 'billing']);

    expect(fn () => app(CreatorReportAccess::class)->show($billing, $evaluation->id))
        ->toThrow(AuthorizationException::class);
});

it('rejects evaluations that are not completed', function () {
    [$evaluation, $admin] = creatorReportFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    $organization = $evaluation->request->organization;
    $creator = User::factory()->create();
    $organization->users()->attach($creator, ['role' => 'editor']);

    DB::table('evaluations')
        ->where('id', $evaluation->id)
        ->update(['status' => 'in_progress']);

    $evaluation->refresh();

    expect(fn () => app(CreatorReportAccess::class)->show($creator, $evaluation->id))
        ->toThrow(AuthorizationException::class);
});

it('renders the creator report through Livewire', function () {
    [$evaluation, $admin] = creatorReportFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    $creator = User::factory()->create();
    $evaluation->request->organization->users()->attach($creator, ['role' => 'editor']);

    Livewire::actingAs($creator)
        ->test(ShowReport::class, ['evaluationId' => $evaluation->id])
        ->assertStatus(200)
        ->assertSee('Report')
        ->assertSee('Criterion results')
        ->assertSee('Strengths')
        ->assertSee('Weaknesses')
        ->assertSee('validated')
        ->assertSee('Private Auditor evidence and deliberation are intentionally excluded.');
});

it('blocks Livewire report access for another tenant', function () {
    [$evaluation, $admin] = creatorReportFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    Livewire::actingAs(User::factory()->create())
        ->test(ShowReport::class, ['evaluationId' => $evaluation->id])
        ->assertStatus(403);
});
