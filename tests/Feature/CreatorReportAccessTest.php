<?php

declare(strict_types=1);

use App\Models\ReportVersion;
use App\Models\Validation;
use App\Models\ValidationBadge;
use App\Models\User;
use App\Services\CreatorReportAccess;
use App\Services\DomainStateTransitionException;
use App\Services\ReportVersioning;
use Livewire\Livewire;
use App\Livewire\Creator\Reports\ShowReport;
use Illuminate\Auth\Access\AuthorizationException;

it('returns only creator-permitted report and validation data', function () {
    [$evaluation, $admin] = decisionFixture();
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
        ->and($data['report']['current']['version_number'])->toBe(1)
        ->and($data['validation']['status'])->toBe('active')
        ->and($data['validation']['badge']['embed_version'])->toBe('1')
        ->and($data)->not->toHaveKey('auditor_evaluations')
        ->and($data)->not->toHaveKey('assignments')
        ->and($data['evaluation'])->not->toHaveKey('decision_rationale');
});

it('exposes report versions as read-only historical snapshots', function () {
    [$evaluation, $admin] = decisionFixture();
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
        ->and(collect($data['report']['versions'])->firstWhere('id', $first->id)['abstract'])->not->toBe('Corrected report.');

    $version = ReportVersion::query()->findOrFail($first->id);
    $version->abstract = 'tampered';

    expect(fn () => $version->save())->toThrow(DomainStateTransitionException::class);
});

it('rejects users outside the evaluation organization', function () {
    [$evaluation, $admin] = decisionFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    expect(fn () => app(CreatorReportAccess::class)->show(User::factory()->create(), $evaluation->id))
        ->toThrow(AuthorizationException::class);
});

it('renders the creator report through Livewire', function () {
    [$evaluation, $admin] = decisionFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    $creator = User::factory()->create();
    $evaluation->request->organization->users()->attach($creator, ['role' => 'editor']);

    Livewire::actingAs($creator)
        ->test(ShowReport::class, ['evaluationId' => $evaluation->id])
        ->assertStatus(200)
        ->assertSee('Report')
        ->assertSee('validated')
        ->assertSee('Private Auditor evidence and deliberation are intentionally excluded.');
});

it('blocks Livewire report access for another tenant', function () {
    [$evaluation, $admin] = decisionFixture();
    app(ReportVersioning::class)->createInitial($evaluation, $admin);

    expect(fn () => Livewire::actingAs(User::factory()->create())
        ->test(ShowReport::class, ['evaluationId' => $evaluation->id]))
        ->toThrow(AuthorizationException::class);
});
