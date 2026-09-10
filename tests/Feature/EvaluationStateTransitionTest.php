<?php

declare(strict_types=1);

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\StandardVersion;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationStateTransition;
use Illuminate\Support\Facades\DB;

function evaluationForTransition(EvaluationStatus $status = EvaluationStatus::Pending): Evaluation
{
    $organization = DB::table('organizations')->insertGetId([
        'name' => 'Transition Test',
        'slug' => 'transition-test-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::create([
        'organization_id' => $organization,
        'title' => 'Course',
        'slug' => 'transition-course-'.uniqid(),
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'v1.0',
        'title_snapshot' => 'Course',
        'version' => '1.0',
        'status' => 'draft',
    ]);

    $request = EvaluationRequest::create([
        'organization_id' => $organization,
        'product_id' => $product->id,
        'service_package' => 'standard',
        'complexity' => 'standard',
        'quoted_price' => 100,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $standard = EvaluationStandard::create([
        'name' => 'Transition Standard',
        'slug' => 'transition-standard-'.uniqid(),
        'description' => 'Test standard',
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'effective',
    ]);

    return Evaluation::create([
        'evaluation_request_id' => $request->id,
        'product_release_id' => $release->id,
        'standard_version_id' => $version->id,
        'status' => $status,
    ]);
}

it('persists the Product as immutable Evaluation provenance', function () {
    $evaluation = evaluationForTransition();

    expect($evaluation->product_id)->toBe($evaluation->productRelease->product_id)
        ->and($evaluation->product->is($evaluation->productRelease->product))->toBeTrue();

    expect(fn () => $evaluation->update(['product_id' => $evaluation->product_id + 1]))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects an Evaluation whose Product does not match its Product Release', function () {
    $evaluation = evaluationForTransition();
    $otherOrganization = DB::table('organizations')->insertGetId([
        'name' => 'Other Organization',
        'slug' => 'other-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherProduct = Product::create([
        'organization_id' => $otherOrganization,
        'title' => 'Other Course',
        'slug' => 'other-course-'.uniqid(),
    ]);

    expect(fn () => Evaluation::create([
        'evaluation_request_id' => $evaluation->evaluation_request_id,
        'product_id' => $otherProduct->id,
        'product_release_id' => $evaluation->product_release_id,
        'standard_version_id' => $evaluation->standard_version_id,
        'status' => EvaluationStatus::Pending,
    ]))->toThrow(DomainStateTransitionException::class);
});

it('moves evaluations through explicit lifecycle states and audits the transition', function () {
    $evaluation = evaluationForTransition();

    app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::InProgress);

    expect($evaluation->fresh()->status)->toBe(EvaluationStatus::InProgress)
        ->and($evaluation->fresh()->started_at)->not->toBeNull()
        ->and($evaluation->fresh()->submitted_at)->toBeNull()
        ->and(DB::table('audit_logs')->where('event', 'evaluation.status_changed')->count())->toBe(1);
});

it('records submission and internal review timestamps through controlled transitions', function () {
    $evaluation = evaluationForTransition();
    $transition = app(EvaluationStateTransition::class);

    $transition->transition($evaluation, EvaluationStatus::InProgress);
    $transition->transition($evaluation, EvaluationStatus::InternalReview);

    expect($evaluation->fresh()->submitted_at)->not->toBeNull()
        ->and($evaluation->fresh()->internal_reviewed_at)->toBeNull();

    $transition->transition($evaluation, EvaluationStatus::ReadyForDecision);

    expect($evaluation->fresh()->internal_reviewed_at)->not->toBeNull();
});

it('rejects invalid evaluation lifecycle transitions', function () {
    $evaluation = evaluationForTransition();

    expect(fn () => app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::Completed))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires a recorded decision before completion', function () {
    $evaluation = evaluationForTransition(EvaluationStatus::ReadyForDecision);

    expect(fn () => app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::Completed))
        ->toThrow(DomainStateTransitionException::class);

    $evaluation->update(['decision' => 'validated']);

    app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::Completed);

    expect($evaluation->fresh()->status)->toBe(EvaluationStatus::Completed)
        ->and($evaluation->fresh()->completed_at)->not->toBeNull();
});

it('does not allow completed evaluations to move again', function () {
    $evaluation = evaluationForTransition(EvaluationStatus::Completed);

    expect(fn () => app(EvaluationStateTransition::class)->transition($evaluation, EvaluationStatus::InternalReview))
        ->toThrow(DomainStateTransitionException::class);
});
