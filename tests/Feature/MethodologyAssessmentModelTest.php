<?php

declare(strict_types=1);

use App\Enums\CriterionAssessment;
use App\Enums\EvaluationStatus;
use App\Enums\PlatformRole;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\StandardVersionGovernance;
use Illuminate\Support\Facades\DB;
use ValueError;

function methodologyAssessmentFixture(): array
{
    $organization = DB::table('organizations')->insertGetId([
        'name' => 'Assessment Test',
        'slug' => 'assessment-test-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::create([
        'organization_id' => $organization,
        'title' => 'Assessment Course',
        'slug' => 'assessment-course-'.uniqid(),
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'v1.0',
        'title_snapshot' => 'Assessment Course',
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
        'name' => 'Assessment Standard',
        'slug' => 'assessment-standard-'.uniqid(),
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
    $assignment = AuditorAssignment::create([
        'evaluation_id' => $evaluation->id,
        'auditor_id' => $auditor->id,
        'sequence' => 1,
        'status' => 'accepted',
        'assigned_at' => now(),
        'accepted_at' => now(),
    ]);

    $auditorEvaluation = AuditorEvaluation::create([
        'evaluation_id' => $evaluation->id,
        'auditor_assignment_id' => $assignment->id,
        'version' => 1,
        'status' => 'submitted',
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    return [$version, $auditorEvaluation];
}

test('standard versions persist the controlled assessment scale and decision thresholds', function () {
    [$version] = methodologyAssessmentFixture();

    expect($version->score_anchors)->toBe(StandardVersion::DEFAULT_SCORE_ANCHORS)
        ->and($version->decision_thresholds)->toBe(StandardVersion::DEFAULT_DECISION_THRESHOLDS)
        ->and($version->scoreAnchorFor(CriterionAssessment::Exceeds))->toBe(['min' => 90, 'max' => 100])
        ->and($version->scoreAnchorFor(CriterionAssessment::InsufficientEvidence))->toBeNull()
        ->and($version->decisionThreshold('overall_minimum'))->toBe(75.0);
});

test('criterion results accept only scores inside the standard version anchor for their assessment', function () {
    [$version, $auditorEvaluation] = methodologyAssessmentFixture();

    $cases = [
        [CriterionAssessment::Exceeds, 95],
        [CriterionAssessment::Meets, 80],
        [CriterionAssessment::PartiallyMeets, 60],
        [CriterionAssessment::DoesNotMeet, 20],
        [CriterionAssessment::InsufficientEvidence, null],
        [CriterionAssessment::NotApplicable, null],
    ];

    foreach ($cases as $index => [$assessment, $score]) {
        $criterion = Criterion::create([
            'standard_version_id' => $version->id,
            'code' => 'A-'.$index,
            'name' => 'Assessment '.$index,
            'category' => 'D1',
            'sequence' => $index + 1,
            'weight' => 10,
            'is_mandatory' => false,
        ]);

        $result = CriterionResult::create([
            'auditor_evaluation_id' => $auditorEvaluation->id,
            'criterion_id' => $criterion->id,
            'assessment' => $assessment,
            'score' => $score,
            'rationale' => 'Test rationale.',
            'confidence' => 90,
        ]);

        expect($result->assessment)->toBe($assessment)
            ->and($result->score)->toBe($score === null ? null : (float) $score);
    }
});

test('criterion results reject assessment and score mismatches', function () {
    [$version, $auditorEvaluation] = methodologyAssessmentFixture();

    $criterion = Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'A-01',
        'name' => 'Assessment',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 10,
        'is_mandatory' => false,
    ]);

    expect(fn () => CriterionResult::create([
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'criterion_id' => $criterion->id,
        'assessment' => CriterionAssessment::Meets,
        'score' => 70,
    ]))->toThrow(DomainStateTransitionException::class);

    expect(fn () => CriterionResult::create([
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'criterion_id' => $criterion->id,
        'assessment' => CriterionAssessment::Meets,
        'score' => null,
    ]))->toThrow(DomainStateTransitionException::class);

    expect(fn () => CriterionResult::create([
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'criterion_id' => $criterion->id,
        'assessment' => CriterionAssessment::NotApplicable,
        'score' => 80,
    ]))->toThrow(DomainStateTransitionException::class);

    expect(fn () => CriterionResult::create([
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'criterion_id' => $criterion->id,
        'assessment' => 'invalid_assessment',
        'score' => 80,
    ]))->toThrow(ValueError::class);
});

test('invalid versioned scoring configuration cannot be scheduled', function () {
    $organization = DB::table('organizations')->insertGetId([
        'name' => 'Governance Test',
        'slug' => 'governance-test-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $standard = EvaluationStandard::create([
        'name' => 'Governance Standard',
        'slug' => 'governance-standard-'.uniqid(),
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '2.0',
        'status' => 'draft',
        'effective_at' => now()->addDay(),
        'score_anchors' => [
            'exceeds' => ['min' => 90, 'max' => 100],
        ],
    ]);

    Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'D1-01',
        'name' => 'Governance criterion',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 10,
        'is_mandatory' => true,
    ]);

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    expect(fn () => app(StandardVersionGovernance::class)->schedule($version, $admin))
        ->toThrow(DomainStateTransitionException::class);
});
