<?php

declare(strict_types=1);

use App\Enums\CriterionAssessment;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\AuditorEvaluationWorkspace;
use App\Services\DomainStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('renders the authenticated auditors frozen evaluation workspace', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $this->actingAs($auditor)
        ->get('/auditor/assignments/'.$auditorEvaluation->assignment->id.'/evaluation')
        ->assertSuccessful()
        ->assertSee('Evaluation context')
        ->assertSee('Course')
        ->assertSee('TEST-01')
        ->assertSee('Test criterion')
        ->assertSee('Meets');
});

it('rejects another auditors evaluation workspace', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    [$otherEvaluation] = auditorEvaluationFixture();
    clearAuditor($otherEvaluation->assignment->auditor);

    $this->actingAs($auditor)
        ->get('/auditor/assignments/'.$otherEvaluation->assignment->id.'/evaluation')
        ->assertForbidden();
});

it('rejects evaluation work when annual clearance is missing', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;

    expect(fn () => app(AuditorEvaluationWorkspace::class)->findFor($auditor, $auditorEvaluation->assignment->id))
        ->toThrow(AuthorizationException::class);
});

it('orders criteria by the frozen standard version sequence', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $standardVersion = $auditorEvaluation->evaluation->standardVersion;
    Criterion::create([
        'standard_version_id' => $standardVersion->id,
        'code' => 'TEST-02',
        'name' => 'Later criterion',
        'sequence' => 20,
        'weight' => 10,
        'is_mandatory' => false,
        'voting_mode' => 'individual',
    ]);
    $earlier = Criterion::create([
        'standard_version_id' => $standardVersion->id,
        'code' => 'TEST-00',
        'name' => 'Earlier criterion',
        'sequence' => 1,
        'weight' => 10,
        'is_mandatory' => false,
        'voting_mode' => 'individual',
    ]);

    $criteria = app(AuditorEvaluationWorkspace::class)->criteria(
        app(AuditorEvaluationWorkspace::class)->findFor($auditor, $auditorEvaluation->assignment->id),
    );
    $earlierIndex = $criteria->search(fn (Criterion $criterion): bool => $criterion->is($earlier));
    $laterIndex = $criteria->search(fn (Criterion $criterion): bool => $criterion->sequence === 20);

    expect($earlierIndex)->toBeInt()
        ->and($laterIndex)->toBeInt()
        ->and($earlierIndex)->toBeLessThan($laterIndex);
});

it('saves a methodology controlled scored draft', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $criterionId = $auditorEvaluation->criterionResults->first()->criterion_id;

    $result = app(AuditorEvaluationWorkspace::class)->saveDraft(
        $auditor,
        $auditorEvaluation,
        $criterionId,
        CriterionAssessment::Exceeds->value,
        95,
        'The criterion is strongly supported by the available evidence.',
        90,
    );

    expect($result->assessment)->toBe(CriterionAssessment::Exceeds)
        ->and((float) $result->score)->toBe(95.0)
        ->and($result->rationale)->toContain('strongly supported');
});

it('rejects invalid assessment values and criteria from another standard version', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $workspace = app(AuditorEvaluationWorkspace::class);
    $criterionId = $auditorEvaluation->criterionResults->first()->criterion_id;

    expect(fn () => $workspace->saveDraft($auditor, $auditorEvaluation, $criterionId, 'invalid', 80, 'Reason', 80))
        ->toThrow(ValidationException::class);

    $otherVersion = StandardVersion::create([
        'evaluation_standard_id' => $auditorEvaluation->evaluation->standardVersion->evaluation_standard_id,
        'version' => '2.0',
        'status' => 'draft',
    ]);
    $otherCriterion = Criterion::create([
        'standard_version_id' => $otherVersion->id,
        'code' => 'OTHER-01',
        'name' => 'Other version criterion',
        'sequence' => 1,
        'weight' => 10,
        'is_mandatory' => false,
        'voting_mode' => 'individual',
    ]);

    expect(fn () => $workspace->saveDraft($auditor, $auditorEvaluation, $otherCriterion->id, CriterionAssessment::Meets->value, 80, 'Reason', 80))
        ->toThrow(ValidationException::class);
});

it('rejects scores that do not match the selected assessment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $workspace = app(AuditorEvaluationWorkspace::class);
    $criterionId = $auditorEvaluation->criterionResults->first()->criterion_id;

    expect(fn () => $workspace->saveDraft($auditor, $auditorEvaluation, $criterionId, CriterionAssessment::Meets->value, 95, 'Reason', 80))
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $workspace->saveDraft($auditor, $auditorEvaluation, $criterionId, CriterionAssessment::NotApplicable->value, 80, 'Reason', 80))
        ->toThrow(ValidationException::class);
});

it('does not allow a second auditor to overwrite the first auditors result', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);

    $otherAuditor = User::factory()->create();
    clearAuditor($otherAuditor);

    $criterionId = $auditorEvaluation->criterionResults->first()->criterion_id;

    expect(fn () => app(AuditorEvaluationWorkspace::class)->saveDraft(
        $otherAuditor,
        $auditorEvaluation,
        $criterionId,
        CriterionAssessment::Exceeds->value,
        95,
        'Unauthorized overwrite',
        95,
    ))->toThrow(AuthorizationException::class);

    expect(CriterionResult::query()->whereKey($auditorEvaluation->criterionResults->first()->id)->value('rationale'))
        ->toBe('Sufficient evidence.');
});

it('rejects draft mutation after the evaluation is locked', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    clearAuditor($auditor);
    $auditorEvaluation->update([
        'status' => 'submitted',
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    $criterionId = $auditorEvaluation->criterionResults->first()->criterion_id;

    expect(fn () => app(AuditorEvaluationWorkspace::class)->saveDraft(
        $auditor,
        $auditorEvaluation,
        $criterionId,
        CriterionAssessment::Exceeds->value,
        95,
        'Late mutation',
        95,
    ))->toThrow(AuthorizationException::class);
});
