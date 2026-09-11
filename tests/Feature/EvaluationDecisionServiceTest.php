<?php

declare(strict_types=1);

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionVotingMode;
use App\Enums\EvaluationStatus;
use App\Enums\EvidenceSufficiency;
use App\Enums\NotificationEventType;
use App\Enums\PlatformRole;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ConflictDeclaration;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\Evaluation;
use App\Models\EvaluationDecision;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\StandardVersion;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationDecisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\DatabaseNotification;

function decisionFixture(float $score = 80, bool $withSubmission = true): array
{
    $organization = DB::table('organizations')->insertGetId([
        'name' => 'Decision Test',
        'slug' => 'decision-test-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::create([
        'organization_id' => $organization,
        'title' => 'Decision Course',
        'slug' => 'decision-course-'.uniqid(),
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'v1.0',
        'title_snapshot' => 'Decision Course',
        'version' => '1.0',
        'status' => 'draft',
    ]);

    $request = EvaluationRequest::create([
        'organization_id' => $organization,
        'product_id' => $product->id,
        'product_release_id' => $release->id,
        'service_package' => 'standard',
        'complexity' => 'standard',
        'quoted_price' => 100,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $standard = EvaluationStandard::create([
        'name' => 'Decision Standard',
        'slug' => 'decision-standard-'.uniqid(),
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'effective',
    ]);

    $evaluation = Evaluation::create([
        'evaluation_request_id' => $request->id,
        'product_release_id' => $release->id,
        'standard_version_id' => $version->id,
        'status' => EvaluationStatus::ReadyForDecision,
    ]);

    $auditor = User::factory()->create();
    $decider = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    $assignment = AuditorAssignment::create([
        'evaluation_id' => $evaluation->id,
        'auditor_id' => $auditor->id,
        'sequence' => 1,
        'status' => 'accepted',
        'assigned_at' => now(),
        'accepted_at' => now(),
    ]);

    ConflictDeclaration::create([
        'evaluation_id' => $evaluation->id,
        'auditor_assignment_id' => $assignment->id,
        'declaration_type' => 'assignment',
        'disclosure' => 'No known conflict.',
        'outcome' => 'cleared',
        'determined_by' => $decider->id,
        'determined_at' => now(),
    ]);

    $auditorEvaluation = null;
    if ($withSubmission) {
        $auditorEvaluation = AuditorEvaluation::create([
            'evaluation_id' => $evaluation->id,
            'auditor_assignment_id' => $assignment->id,
            'version' => 1,
            'status' => 'submitted',
            'submitted_at' => now(),
            'locked_at' => now(),
            'evidence_sufficiency' => EvidenceSufficiency::Sufficient,
            'audience_promise_coherence' => AudiencePromiseCoherence::Coherent,
        ]);
    }

    foreach (range(1, 10) as $number) {
        $criterion = Criterion::create([
            'standard_version_id' => $version->id,
            'code' => 'D'.$number.'-01',
            'name' => 'Dimension '.$number,
            'category' => 'D'.$number,
            'sequence' => $number,
            'weight' => 10,
            'is_mandatory' => $number === 1,
            'voting_mode' => CriterionVotingMode::Majority,
        ]);

        if ($auditorEvaluation !== null) {
            $resultScore = $number === 1 ? $score : 80;
            CriterionResult::create([
                'auditor_evaluation_id' => $auditorEvaluation->id,
                'criterion_id' => $criterion->id,
                'assessment' => $resultScore >= 75 ? 'meets' : 'partially_meets',
                'score' => $resultScore,
                'rationale' => 'Documented test rationale.',
                'confidence' => 90,
                'submitted_at' => now(),
            ]);
        }
    }

    return [$evaluation, $decider, $auditorEvaluation];
}

test('evaluation decision validates when all gates are satisfied', function () {
    [$evaluation, $decider] = decisionFixture();

    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    expect($decision)->toBeInstanceOf(EvaluationDecision::class)
        ->and($decision->decision)->toBe('validated')
        ->and((float) $decision->evaluation->overall_score)->toBe(80.0)
        ->and($decision->evaluation->status)->toBe(EvaluationStatus::Completed)
        ->and($decision->evaluation->criterionVotes()->count())->toBe(10);
});

test('evaluation decision rejects a mandatory criterion below threshold', function () {
    [$evaluation, $decider] = decisionFixture(70);

    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $rationale = json_decode($decision->rationale, true, 512, JSON_THROW_ON_ERROR);

    expect($decision->decision)->toBe('not_validated')
        ->and($decision->evaluation->status)->toBe(EvaluationStatus::Completed)
        ->and($rationale['blockers'])->toContain('Mandatory criterion D1-01 does not meet the 75/100 threshold.');
});

test('evaluation decision refuses incomplete auditor work', function () {
    [$evaluation, $decider] = decisionFixture(80, false);

    expect(fn () => app(EvaluationDecisionService::class)->decide($evaluation, $decider))
        ->toThrow(DomainStateTransitionException::class);
});

test('evaluation decision requires a platform administrator', function () {
    [$evaluation] = decisionFixture();
    $nonAdmin = User::factory()->create();

    expect(fn () => app(EvaluationDecisionService::class)->decide($evaluation, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);
});

test('insufficient evidence blocks validation', function () {
    [$evaluation, $decider, $auditorEvaluation] = decisionFixture();
    DB::table('auditor_evaluations')->where('id', $auditorEvaluation->id)->update([
        'evidence_sufficiency' => EvidenceSufficiency::Insufficient->value,
    ]);

    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $rationale = json_decode($decision->rationale, true, 512, JSON_THROW_ON_ERROR);

    expect($decision->decision)->toBe('not_validated')
        ->and($rationale['blockers'][0])->toContain('does not establish sufficient evidence');
});

test('unresolved audience promise coherence blocks validation', function () {
    [$evaluation, $decider, $auditorEvaluation] = decisionFixture();
    DB::table('auditor_evaluations')->where('id', $auditorEvaluation->id)->update([
        'audience_promise_coherence' => AudiencePromiseCoherence::Unresolved->value,
    ]);

    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $rationale = json_decode($decision->rationale, true, 512, JSON_THROW_ON_ERROR);

    expect($decision->decision)->toBe('not_validated')
        ->and($rationale['blockers'][0])->toContain('does not establish coherence with the stated audience and promise');
});

test('central claims without auditor evidence block validation', function () {
    [$evaluation, $decider] = decisionFixture();
    $evaluation->productRelease->product->update(['claimed_outcomes' => ['Learn the subject']]);

    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);
    $rationale = json_decode($decision->rationale, true, 512, JSON_THROW_ON_ERROR);

    expect($decision->decision)->toBe('not_validated')
        ->and($rationale['blockers'])->toContain('Central product claims do not have evidence coverage from every submitted Auditor evaluation.');
});

test('completed evaluations cannot be mutated through the model', function () {
    [$evaluation, $decider] = decisionFixture();
    app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    $evaluation->product_release_id = $evaluation->product_release_id + 1;
    expect(fn () => $evaluation->save())
        ->toThrow(DomainStateTransitionException::class);

    $evaluation->refresh();
    $evaluation->decision = 'tampered';
    expect(fn () => $evaluation->save())
        ->toThrow(DomainStateTransitionException::class);
});

test('evaluation provenance cannot be changed after creation', function () {
    [$evaluation] = decisionFixture();
    $evaluation->standard_version_id = $evaluation->standard_version_id + 1;

    expect(fn () => $evaluation->save())
        ->toThrow(DomainStateTransitionException::class);
});

test('evaluation decisions cannot be changed or deleted', function () {
    [$evaluation, $decider] = decisionFixture();
    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    $decision->rationale = 'tampered';
    expect(fn () => $decision->save())
        ->toThrow(DomainStateTransitionException::class);

    expect(fn () => $decision->delete())
        ->toThrow(DomainStateTransitionException::class);
});

test('recording an evaluation decision notifies the creator organization', function () {
    [$evaluation, $decider] = decisionFixture();
    $creator = User::factory()->create();
    $evaluation->request->organization->users()->attach($creator, ['role' => 'owner']);

    $decision = app(EvaluationDecisionService::class)->decide($evaluation, $decider);

    $notification = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $creator->id)
        ->where('type', WorkflowNotification::class)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification?->data['event_type'])->toBe(NotificationEventType::EvaluationDecisionRecorded->value)
        ->and($notification?->data['context'])->toMatchArray([
            'evaluation_decision_id' => $decision->id,
            'evaluation_id' => $evaluation->id,
        ]);
});
