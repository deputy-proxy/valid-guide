<?php

declare(strict_types=1);

use App\Models\AuditorCompetency;
use App\Models\AuditorProfile;
use App\Models\AuditorAnnualConflictDeclaration;
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
    $auditor = eligibleAuditorForEvaluation($evaluation);
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $service = app(AuditorAssignmentCreation::class);

    $service->create($evaluation, $auditor, $admin, 2, 15000, 'EUR', Carbon::now()->addDays(3));

    expect(fn () => $service->create($evaluation, $auditor, $admin, 3, 15000, 'EUR', Carbon::now()->addDays(3)))
        ->toThrow(DomainStateTransitionException::class);
});
