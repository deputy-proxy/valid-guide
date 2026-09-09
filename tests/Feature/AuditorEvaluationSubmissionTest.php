<?php

declare(strict_types=1);

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
use App\Services\AuditorEvaluationSubmission;
use App\Services\ConflictDeclarationDecision;
use App\Services\DomainStateTransitionException;
use Illuminate\Support\Facades\DB;

function auditorEvaluationFixture(): array
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
    $criterion = Criterion::create(['standard_version_id' => $version->id, 'code' => 'TEST-01', 'name' => 'Test criterion', 'weight' => 100, 'is_mandatory' => true]);
    $auditorEvaluation = AuditorEvaluation::create(['evaluation_id' => $evaluation->id, 'auditor_assignment_id' => $assignment->id, 'version' => 1, 'status' => 'draft']);
    $result = CriterionResult::create(['auditor_evaluation_id' => $auditorEvaluation->id, 'criterion_id' => $criterion->id, 'assessment' => 'meets', 'score' => 80, 'rationale' => 'Sufficient evidence.', 'confidence' => 90]);

    return [$auditorEvaluation, $result, $declaration];
}

test('submits and locks an auditor evaluation atomically', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    $submitted = app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation);
    $fresh = $submitted->fresh();
    expect($fresh->status)->toBe('submitted')->and($fresh->submitted_at)->not->toBeNull()->and($fresh->locked_at)->not->toBeNull()->and($result->fresh()->submitted_at)->not->toBeNull()->and(DB::table('audit_logs')->where('event', 'auditor_evaluation.submitted')->count())->toBe(1);
});

test('rejects submission for an unaccepted assignment', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $auditorEvaluation->assignment()->update(['status' => 'offered']);
    expect(fn () => app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation))->toThrow(DomainStateTransitionException::class);
});

test('prevents changes and deletion after auditor evaluation submission', function () {
    [$auditorEvaluation, $result] = auditorEvaluationFixture();
    app(AuditorEvaluationSubmission::class)->submit($auditorEvaluation);
    $auditorEvaluation->refresh();
    $result->refresh();
    expect(fn () => $auditorEvaluation->update(['status' => 'draft']))->toThrow(DomainStateTransitionException::class);
    expect(fn () => $result->update(['score' => 50]))->toThrow(DomainStateTransitionException::class);
    expect(fn () => $result->delete())->toThrow(DomainStateTransitionException::class);
    expect(fn () => $auditorEvaluation->delete())->toThrow(DomainStateTransitionException::class);
});

test('allows an explicit conflict decision and keeps it immutable', function () {
    [, , $declaration] = auditorEvaluationFixture();
    $decisionMaker = User::factory()->create();
    DB::table('conflict_declarations')->where('id', $declaration->id)->update(['outcome' => 'potential_conflict', 'determined_by' => null, 'determined_at' => null]);
    $declaration->refresh();
    $decided = app(ConflictDeclarationDecision::class)->decide($declaration, 'cleared', $decisionMaker);
    $decided->refresh();
    expect($decided->outcome)->toBe('cleared')->and($decided->determined_by)->toBe($decisionMaker->id)->and($decided->determined_at)->not->toBeNull();
    expect(fn () => $decided->update(['outcome' => 'disqualified']))->toThrow(DomainStateTransitionException::class);
});
