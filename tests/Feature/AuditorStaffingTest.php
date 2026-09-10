<?php

declare(strict_types=1);

use App\Enums\EvaluationComplexity;
use App\Models\User;
use App\Services\AuditorAssignmentCreation;
use App\Services\AuditorAssignmentStateTransition;
use App\Services\AuditorStaffing;
use App\Services\DomainStateTransitionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('resolves the methodology Auditor count from evaluation complexity', function () {
    $expected = [
        EvaluationComplexity::Simple->value => 1,
        EvaluationComplexity::Standard->value => 1,
        EvaluationComplexity::Complex->value => 3,
        EvaluationComplexity::Exceptional->value => 5,
    ];

    foreach ($expected as $complexity => $count) {
        [$auditorEvaluation] = auditorEvaluationFixture();
        $evaluation = $auditorEvaluation->evaluation;
        $evaluation->request->update(['complexity' => $complexity]);

        expect(app(AuditorStaffing::class)->requiredCount($evaluation))->toBe($count);
    }
});

it('rejects an even or incomplete Auditor staffing configuration', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $version = $auditorEvaluation->evaluation->standardVersion;
    DB::table('standard_versions')->where('id', $version->id)->update([
        'auditor_staffing_rules' => json_encode([
            'simple' => 1,
            'standard' => 2,
            'complex' => 3,
            'exceptional' => 5,
        ], JSON_THROW_ON_ERROR),
    ]);
    $version->refresh();

    expect(fn () => app(AuditorStaffing::class)->requiredCount($auditorEvaluation->evaluation))
        ->toThrow(DomainStateTransitionException::class);
});

it('prevents assignment creation beyond the methodology Auditor count', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $auditorTwo = User::factory()->create();
    $auditorThree = User::factory()->create();

    $evaluation->assignments()->createMany([
        ['auditor_id' => $auditorTwo->id, 'sequence' => 2, 'status' => 'offered', 'assigned_at' => now()],
        ['auditor_id' => $auditorThree->id, 'sequence' => 3, 'status' => 'offered', 'assigned_at' => now()],
    ]);

    $admin = User::factory()->create(['platform_role' => 'admin']);
    $auditor = User::factory()->create();

    expect(fn () => app(AuditorAssignmentCreation::class)->create(
        $evaluation,
        $auditor,
        $admin,
        4,
        15000,
        'EUR',
        Carbon::now()->addDays(3),
    ))->toThrow(DomainStateTransitionException::class);
});

it('does not allow Auditor work to start before the required panel is staffed', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);
    $assignment = $auditorEvaluation->assignment;
    $assignment->update(['status' => 'offered']);

    expect(fn () => app(AuditorAssignmentStateTransition::class)->transition($assignment, 'accepted'))
        ->toThrow(DomainStateTransitionException::class);
});

it('requires exactly the methodology Auditor count for final submitted evaluations', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $evaluation = $auditorEvaluation->evaluation;
    $evaluation->request->update(['complexity' => EvaluationComplexity::Complex]);

    $auditorEvaluation->update(['status' => 'submitted', 'locked_at' => now()]);

    expect(fn () => app(AuditorStaffing::class)->assertSubmittedCount($evaluation))
        ->toThrow(DomainStateTransitionException::class);
});
