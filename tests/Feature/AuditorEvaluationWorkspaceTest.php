<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\CriterionAssessment;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorProfile;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\User;
use App\Services\AuditorEvaluationWorkspace;
use App\Services\DomainStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function prepareClearedAuditorForWorkspace(User $auditor): void
{
    $reviewer = User::factory()->create();

    AuditorProfile::query()->create([
        'auditor_id' => $auditor->id,
        'status' => AuditorProfileStatus::Approved,
        'approved_by' => $reviewer->id,
        'approved_at' => now(),
    ]);

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $auditor->id,
        'year' => now()->year,
        'disclosure' => 'No known conflicts.',
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_by' => $reviewer->id,
        'determined_at' => now(),
    ]);
}

it('renders the authenticated auditors frozen evaluation workspace', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    prepareClearedAuditorForWorkspace($auditor);

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
    prepareClearedAuditorForWorkspace($auditor);

    [$otherEvaluation] = auditorEvaluationFixture();
    prepareClearedAuditorForWorkspace($otherEvaluation->assignment->auditor);

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
    prepareClearedAuditorForWorkspace($auditor);

    $standardVersion = $auditorEvaluation->evaluation->standardVersion;
    $later = Criterion::create([
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

    expect($criteria->pluck('id')->all())->toBe([$earlier->id, $auditorEvaluation->criterionResults->first()->criterion_id, $later->id]);
});

it('saves a methodology controlled scored draft', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditor = $auditorEvaluation->assignment->auditor;
    prepareClearedAuditorForWorkspace($auditor);
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
    prepareClearedAuditorForWorkspace($auditor);
    $workspace = app(AuditorEvaluationWorkspace::class);
    $criterionId = $auditorEvaluation->criterionResults->first()->criterion_id;

    expect(fn () => $workspace->saveDraft($auditor, $auditorEvaluation, $criterionId, 'invalid', 80, 'Reason', 80))
        ->toThrow(ValidationException::class);

    $otherVersion = $auditorEvaluation->evaluation->standardVersion->replicate();
    $otherVersion->version = '2.0';
    $otherVersion->status = 'draft';
    $otherVersion->save();
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
    prepareClearedAuditorForWorkspace($auditor);
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
    prepareClearedAuditorForWorkspace($auditor);

    $otherAuditor = User::factory()->create();
    prepareClearedAuditorForWorkspace($otherAuditor);

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
    prepareClearedAuditorForWorkspace($auditor);
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
