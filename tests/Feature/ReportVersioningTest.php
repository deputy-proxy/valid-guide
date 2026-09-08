<?php

declare(strict_types=1);

use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use App\Services\ReportVersioning;

it('creates the first report version from a completed evaluation and makes it current', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    $version = app(ReportVersioning::class)->createInitial($evaluation, $decider);

    expect($version)->toBeInstanceOf(ReportVersion::class)
        ->and($version->version_number)->toBe(1)
        ->and($version->report->evaluation_id)->toBe($evaluation->id)
        ->and($version->report->current_version_id)->toBe($version->id)
        ->and($version->published_at)->toBeNull()
        ->and($version->decision_snapshot['decision'])->toBe('validated')
        ->and($version->abstract)->toContain('validated');
});

it('refuses to create an initial report when one already exists', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    app(ReportVersioning::class)->createInitial($evaluation, $decider);

    expect(fn () => app(ReportVersioning::class)->createInitial($evaluation, $decider))
        ->toThrow(DomainStateTransitionException::class);
});

it('creates immutable sequential report revisions', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $first = app(ReportVersioning::class)->createInitial($evaluation, $decider);

    $revision = app(ReportVersioning::class)->createRevision(
        $first->report,
        User::factory()->create(),
        ['sections' => ['summary' => 'Corrected factual wording.']],
        'Updated factual wording.',
        'Corrected a material factual error identified after delivery.',
    );

    expect($revision->version_number)->toBe(2)
        ->and($revision->report->current_version_id)->toBe($revision->id)
        ->and($revision->change_reason)->toBe('Corrected a material factual error identified after delivery.');

    $revision->abstract = 'tampered';

    expect(fn () => $revision->save())
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $revision->delete())
        ->toThrow(DomainStateTransitionException::class);
});

it('requires a reason for report revisions', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $report = app(ReportVersioning::class)->createInitial($evaluation, $decider)->report;

    expect(fn () => app(ReportVersioning::class)->createRevision(
        $report,
        $decider,
        ['sections' => []],
        null,
        '  ',
    ))->toThrow(DomainStateTransitionException::class);
});

it('protects the report itself from deletion', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $report = app(ReportVersioning::class)->createInitial($evaluation, $decider)->report;

    expect(fn () => $report->delete())
        ->toThrow(DomainStateTransitionException::class);
});
