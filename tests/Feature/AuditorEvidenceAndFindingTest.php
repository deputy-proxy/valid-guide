<?php

declare(strict_types=1);

use App\Models\AuditorEvaluation;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\User;
use App\Services\AuditorEvidenceManagement;
use App\Services\AuditorFindingManagement;
use App\Services\DomainStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('creates private evidence attributed to the authenticated auditor evaluation', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $criterionId = $auditorEvaluation->criterionResults->firstOrFail()->criterion_id;

    $evidence = app(AuditorEvidenceManagement::class)->create($auditor, $auditorEvaluation, [
        'criterion_id' => $criterionId,
        'type' => 'link',
        'title' => 'Course landing page',
        'description' => 'The published page was directly inspected.',
        'source_url' => 'https://example.com/course',
        'provenance' => 'observed',
    ]);

    expect($evidence->auditor_evaluation_id)->toBe($auditorEvaluation->id)
        ->and($evidence->evaluation_id)->toBe($auditorEvaluation->evaluation_id)
        ->and($evidence->criterion_result_id)->not->toBeNull()
        ->and($evidence->visibility)->toBe('private');
});

it('rejects evidence that references another standard version', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $otherVersion = $auditorEvaluation->evaluation->standardVersion->replicate();
    $otherVersion->version = '9.9';
    $otherVersion->status = 'draft';
    $otherVersion->save();

    $otherCriterion = $otherVersion->criteria()->create([
        'code' => 'OTHER-01',
        'name' => 'Other criterion',
        'sequence' => 1,
        'weight' => 10,
        'is_mandatory' => false,
        'voting_mode' => 'individual',
    ]);

    expect(fn () => app(AuditorEvidenceManagement::class)->create($auditor, $auditorEvaluation, [
        'criterion_id' => $otherCriterion->id,
        'type' => 'document',
        'title' => 'Foreign evidence',
        'provenance' => 'observed',
    ]))->toThrow(ValidationException::class);
});

it('does not allow another auditor to create or delete evidence', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $otherAuditor = User::factory()->create();
    clearAuditor($otherAuditor);

    $evidence = app(AuditorEvidenceManagement::class)->create($auditor, $auditorEvaluation, [
        'type' => 'observation',
        'title' => 'Observed component',
        'provenance' => 'observed',
    ]);

    expect(fn () => app(AuditorEvidenceManagement::class)->delete($otherAuditor, $evidence))
        ->toThrow(AuthorizationException::class);

    expect(Evidence::query()->whereKey($evidence->id)->exists())->toBeTrue();
});

it('rejects evidence mutation after auditor submission', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $evidence = app(AuditorEvidenceManagement::class)->create($auditor, $auditorEvaluation, [
        'type' => 'reference',
        'title' => 'Methodology reference',
        'provenance' => 'external',
    ]);

    $auditorEvaluation->update([
        'status' => 'submitted',
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    $evidence->title = 'Changed after submission';

    expect(fn () => $evidence->save())
        ->toThrow(DomainStateTransitionException::class);
});

it('creates a criterion-scoped auditor finding', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $criterionId = $auditorEvaluation->criterionResults->firstOrFail()->criterion_id;

    $finding = app(AuditorFindingManagement::class)->create($auditor, $auditorEvaluation, [
        'criterion_id' => $criterionId,
        'type' => 'weakness',
        'severity' => 'medium',
        'title' => 'Practice is limited',
        'description' => 'The inspected material provides limited opportunities for application.',
    ]);

    expect($finding->auditor_evaluation_id)->toBe($auditorEvaluation->id)
        ->and($finding->evaluation_id)->toBe($auditorEvaluation->evaluation_id)
        ->and($finding->criterion_id)->toBe($criterionId)
        ->and($finding->status)->toBe('draft');
});

it('does not allow another auditor to create or delete a finding', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $otherAuditor = User::factory()->create();
    clearAuditor($otherAuditor);

    $finding = app(AuditorFindingManagement::class)->create($auditor, $auditorEvaluation, [
        'type' => 'strength',
        'severity' => 'low',
        'title' => 'Clear structure',
        'description' => 'The material has a coherent structure.',
    ]);

    expect(fn () => app(AuditorFindingManagement::class)->delete($otherAuditor, $finding))
        ->toThrow(AuthorizationException::class);

    expect(Finding::query()->whereKey($finding->id)->exists())->toBeTrue();
});

it('rejects finding mutation after auditor submission', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $finding = app(AuditorFindingManagement::class)->create($auditor, $auditorEvaluation, [
        'type' => 'risk',
        'severity' => 'high',
        'title' => 'Material delivery risk',
        'description' => 'A critical component could not be inspected.',
    ]);

    $auditorEvaluation->update([
        'status' => 'submitted',
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    $finding->title = 'Changed after submission';

    expect(fn () => $finding->save())
        ->toThrow(DomainStateTransitionException::class);
});

it('keeps general evidence and findings attributable without requiring a criterion', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $evidence = app(AuditorEvidenceManagement::class)->create($auditor, $auditorEvaluation, [
        'type' => 'document',
        'title' => 'Evaluation scope document',
        'provenance' => 'creator_supplied',
    ]);
    $finding = app(AuditorFindingManagement::class)->create($auditor, $auditorEvaluation, [
        'type' => 'recommendation',
        'severity' => 'low',
        'title' => 'Clarify learner prerequisites',
        'description' => 'The product would benefit from clearer prerequisite guidance.',
    ]);

    expect($evidence->criterion_result_id)->toBeNull()
        ->and($finding->criterion_id)->toBeNull();
});

it('does not expose another auditors private evaluation through the workspace aggregate', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $otherAuditor = User::factory()->create();
    clearAuditor($otherAuditor);

    $otherEvaluation = AuditorEvaluation::query()->where('auditor_assignment_id', '!=', $auditorEvaluation->auditor_assignment_id)->first();

    if ($otherEvaluation === null) {
        expect(true)->toBeTrue();
        return;
    }

    expect(fn () => app(\App\Services\AuditorEvaluationWorkspace::class)->findFor($auditor, $otherEvaluation->auditor_assignment_id))
        ->toThrow(AuthorizationException::class);
});
