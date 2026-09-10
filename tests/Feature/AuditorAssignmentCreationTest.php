<?php

declare(strict_types=1);

use App\Enums\EvaluationComplexity;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorAssignment;
use App\Models\AuditorProfile;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\AuditorAssignmentCreation;
use App\Services\DomainStateTransitionException;
use Illuminate\Support\Carbon;

function eligibleAuditorForEvaluation($evaluation): User
{
    $auditor = User::factory()->create();
    $product = $evaluation->productRelease->product;
    $product->update(['subject_area' => 'instructional design']);

    AuditorProfile::query()->create([
        'auditor_id' => $auditor->id,
        'status' => 'approved',
        'methodology_literate' => true,
        'format_experience' => [$product->product_type->value],
        'approved_by' => User::factory()->create(['platform_role' => 'admin'])->id,
        'approved_at' => now(),
    ]);

    $auditor->auditorProfile->competencies()->create([
        'topic' => 'instructional design',
        'experience_type' => 'professional',
        'years_experience' => 8,
        'evidence' => 'Verified professional experience.',
        'verified_at' => now(),
        'verified_by' => User::factory()->create(['platform_role' => 'admin'])->id,
    ]);

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $auditor->id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_at' => now(),
    ]);

    return $auditor;
}

it('creates an assignment only for an eligible auditor', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $assignment = app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        2,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    );

    expect($assignment->auditor_id)->toBe($auditor->id)
        ->and($assignment->status)->toBe('offered')
        ->and($assignment->conflictDeclarations()->count())->toBe(1)
        ->and($assignment->compensation()->exists())->toBeTrue();
});

it('rejects an auditor without an approved profile', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    expect(fn () => app(AuditorAssignmentCreation::class)->create(
        $auditorEvaluation->evaluation,
        User::factory()->create(),
        $admin,
        2,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    ))->toThrow(DomainStateTransitionException::class);
});

it('rejects an auditor without verified subject competence', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $auditor = User::factory()->create();
    $product = $evaluation->productRelease->product;
    $product->update(['subject_area' => 'instructional design']);
    $admin = User::factory()->create(['platform_role' => 'admin']);

    AuditorProfile::query()->create([
        'auditor_id' => $auditor->id,
        'status' => 'approved',
        'methodology_literate' => true,
        'format_experience' => [$product->product_type->value],
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    AuditorAnnualConflictDeclaration::query()->create([
        'auditor_id' => $auditor->id,
        'year' => now()->year,
        'outcome' => 'cleared',
        'submitted_at' => now(),
        'determined_at' => now(),
    ]);

    expect(fn () => app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        2,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    ))->toThrow(DomainStateTransitionException::class);
});

it('rejects a second assignment of the same auditor', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $service = app(AuditorAssignmentCreation::class);

    $service->create($evaluation, $auditor, $admin, 2, 15000, 'EUR', Carbon::now()->addDays(3));

    expect(fn () => $service->create($evaluation, $auditor, $admin, 3, 15000, 'EUR', Carbon::now()->addDays(3)))
        ->toThrow(DomainStateTransitionException::class);
});

it('rejects an auditor who is a creator or contributor on the product organization', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $organization = $evaluation->productRelease->product->organization;

    $organization->users()->attach($auditor->id, ['role' => 'editor']);

    expect(fn () => app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        1,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    ))->toThrow(DomainStateTransitionException::class, 'previously participated in this product');
});

it('uses persisted organization membership instead of stale in-memory relationship state', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $organization = $evaluation->productRelease->product->organization;

    $organization->load('users');
    $organization->users()->attach($auditor->id, ['role' => 'owner']);

    expect($organization->relationLoaded('users'))->toBeTrue()
        ->and(fn () => app(AuditorAssignmentCreation::class)->create(
            $evaluation,
            $auditor,
            $admin,
            1,
            15000,
            'EUR',
            Carbon::now()->addDays(3),
        ))->toThrow(DomainStateTransitionException::class, 'previously participated in this product');
});

it('rejects an auditor with prior participation through an earlier evaluation of the same product', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);

    AuditorAssignment::query()->create([
        'evaluation_id' => $evaluation->id,
        'auditor_id' => $auditor->id,
        'sequence' => 1,
        'status' => 'completed',
        'assigned_at' => now()->subDays(10),
        'accepted_at' => now()->subDays(9),
        'completed_at' => now()->subDays(1),
        'compensation_amount_minor' => 15000,
        'compensation_currency' => 'EUR',
        'compensation_status' => 'pending',
    ]);

    $laterEvaluation = Evaluation::query()->create([
        'evaluation_request_id' => $evaluation->evaluation_request_id,
        'product_release_id' => $evaluation->product_release_id,
        'standard_version_id' => $evaluation->standard_version_id,
        'status' => 'pending',
    ]);

    expect(fn () => app(AuditorAssignmentCreation::class)->create(
        $laterEvaluation,
        $auditor,
        $admin,
        1,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    ))->toThrow(DomainStateTransitionException::class, 'previously participated in this product');
});

it('allows an auditor who participated in a different product', function () {
    [$firstAuditorEvaluation] = auditorEvaluationFixture();
    $firstEvaluation = $firstAuditorEvaluation->evaluation;
    $auditor = eligibleAuditorForEvaluation($firstEvaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);

    [$secondAuditorEvaluation] = auditorEvaluationFixture();
    $secondEvaluation = $secondAuditorEvaluation->evaluation;
    $secondEvaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $secondEvaluation->productRelease->product->update(['subject_area' => 'instructional design']);

    $assignment = app(AuditorAssignmentCreation::class)->create(
        $secondEvaluation,
        $auditor,
        $admin,
        1,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    );

    expect($assignment->auditor_id)->toBe($auditor->id);
});

it('does not treat a billing-only organization membership as product participation', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $organization = $evaluation->productRelease->product->organization;

    $organization->users()->attach($auditor->id, ['role' => 'billing']);

    $assignment = app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        1,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    );

    expect($assignment->auditor_id)->toBe($auditor->id);
});
