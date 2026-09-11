<?php

declare(strict_types=1);

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

it('isolates evidence from another auditor and locks it after submission', function () {
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

it('isolates findings from another auditor and locks them after submission', function () {
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

    $auditorEvaluation->update([
        'status' => 'submitted',
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    $finding->title = 'Changed after submission';

    expect(fn () => $finding->save())
        ->toThrow(DomainStateTransitionException::class);
});

it('allows general evidence and findings without a criterion', function () {
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
