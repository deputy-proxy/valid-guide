<?php

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionVotingMode;
use App\Enums\EvidenceSufficiency;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\ConflictDeclaration;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function auditorEvaluationFixture(CriterionVotingMode $votingMode = CriterionVotingMode::Individual): array
{
    $organization = DB::table('organizations')->insertGetId(['name' => 'Auditor Test', 'slug' => 'auditor-test-'.uniqid(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $product = Product::create(['organization_id' => $organization, 'title' => 'Course', 'slug' => 'auditor-course-'.uniqid()]);
    $release = ProductRelease::create(['product_id' => $product->id, 'release_identifier' => 'v1.0', 'title_snapshot' => 'Course', 'version' => '1.0', 'status' => 'draft']);
    $request = EvaluationRequest::create(['organization_id' => $organization, 'product_id' => $product->id, 'product_release_id' => $release->id, 'service_package' => 'standard', 'complexity' => 'standard', 'quoted_price' => 100, 'currency' => 'EUR', 'status' => 'draft']);
    $standard = EvaluationStandard::create(['name' => 'Auditor Standard', 'slug' => 'auditor-standard-'.uniqid()]);
    $version = StandardVersion::create(['evaluation_standard_id' => $standard->id, 'version' => '1.0', 'status' => 'effective']);
    $evaluation = Evaluation::create(['evaluation_request_id' => $request->id, 'product_release_id' => $release->id, 'standard_version_id' => $version->id, 'status' => 'in_progress']);
    $auditor = User::factory()->create();
    $assignment = AuditorAssignment::create(['evaluation_id' => $evaluation->id, 'auditor_id' => $auditor->id, 'sequence' => 1, 'status' => 'accepted', 'assigned_at' => now(), 'accepted_at' => now()]);
    $declaration = ConflictDeclaration::create(['evaluation_id' => $evaluation->id, 'auditor_assignment_id' => $assignment->id, 'declaration_type' => 'assignment', 'disclosure' => 'No known conflict.', 'outcome' => 'cleared', 'determined_by' => $auditor->id, 'determined_at' => now()]);
    $criterion = Criterion::create(['standard_version_id' => $version->id, 'code' => 'TEST-01', 'name' => 'Test criterion', 'weight' => 100, 'is_mandatory' => true, 'voting_mode' => $votingMode]);
    $auditorEvaluation = AuditorEvaluation::create(['evaluation_id' => $evaluation->id, 'auditor_assignment_id' => $assignment->id, 'version' => 1, 'status' => 'draft', 'evidence_sufficiency' => EvidenceSufficiency::Sufficient, 'audience_promise_coherence' => AudiencePromiseCoherence::Coherent]);
    $result = CriterionResult::create(['auditor_evaluation_id' => $auditorEvaluation->id, 'criterion_id' => $criterion->id, 'assessment' => 'meets', 'score' => 80, 'rationale' => 'Sufficient evidence.', 'confidence' => 90]);

    return [$auditorEvaluation, $result, $declaration];
}
