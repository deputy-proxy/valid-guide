<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Filament\Pages\ValidationCalibrationPage;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\CriterionResult;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\ValidationCalibrationService;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

function completeCalibrationEvaluation(int $evaluationId, int $auditorEvaluationId, string $decision, float $score): void
{
    DB::table('evaluations')->where('id', $evaluationId)->update([
        'status' => 'completed',
        'decision' => $decision,
        'overall_score' => $score,
        'completed_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auditor_evaluations')->where('id', $auditorEvaluationId)->update([
        'status' => 'submitted',
        'submitted_at' => now(),
        'locked_at' => now(),
        'updated_at' => now(),
    ]);
}

test('calibration aggregates completed evaluations by methodology version without exposing private evidence', function () {
    [$firstAuditorEvaluation] = auditorEvaluationFixture();
    $firstEvaluation = $firstAuditorEvaluation->evaluation;
    $versionId = $firstEvaluation->standard_version_id;

    completeCalibrationEvaluation($firstEvaluation->id, $firstAuditorEvaluation->id, 'validated', 80);

    for ($index = 0; $index < 4; $index++) {
        [$auditorEvaluation] = auditorEvaluationFixture();
        $evaluation = $auditorEvaluation->evaluation;

        DB::table('evaluations')->where('id', $evaluation->id)->update([
            'standard_version_id' => $versionId,
            'status' => 'completed',
            'decision' => $index === 0 ? 'not_validated' : 'validated',
            'overall_score' => 70 + ($index * 5),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        completeCalibrationEvaluation($evaluation->id, $auditorEvaluation->id, $index === 0 ? 'not_validated' : 'validated', 70 + ($index * 5));
    }

    $report = app(ValidationCalibrationService::class)->report();
    $standardReport = collect($report)->firstWhere('standard_version_id', $versionId);

    expect($standardReport)
        ->not->toBeNull()
        ->and($standardReport['evaluations_completed'])->toBe(5)
        ->and($standardReport['auditor_evaluations'])->toBe(5)
        ->and($standardReport['insufficient_evidence_rate'])->toBe(0.0)
        ->and($standardReport['audience_coherence_rate'])->toBe(1.0)
        ->and($standardReport['validated_rate'])->toBe(0.8)
        ->and($standardReport)->not->toHaveKey('evidence')
        ->and($standardReport)->not->toHaveKey('rationale');
});

test('calibration reports insufficient data instead of manufacturing rates', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    completeCalibrationEvaluation($auditorEvaluation->evaluation_id, $auditorEvaluation->id, 'validated', 80);

    $report = app(ValidationCalibrationService::class)->report()[0];

    expect($report['evaluations_completed'])->toBe(1)
        ->and($report['insufficient_evidence_rate'])->toBeNull()
        ->and($report['audience_coherence_rate'])->toBeNull()
        ->and($report['criterion_agreement_rate'])->toBeNull()
        ->and($report['score_mean'])->toBeNull()
        ->and($report['validated_rate'])->toBeNull()
        ->and($report['review_flags'])->toContain('insufficient_evaluation_sample');
});

test('calibration measures agreement and recurring disagreement across an odd auditor panel', function () {
    [$firstAuditorEvaluation, $firstResult] = auditorEvaluationFixture();
    $evaluation = $firstAuditorEvaluation->evaluation;
    $criterionId = $firstResult->criterion_id;

    completeCalibrationEvaluation($evaluation->id, $firstAuditorEvaluation->id, 'validated', 80);

    foreach ([85, 70] as $sequence => $score) {
        $auditor = User::factory()->create();
        $assignment = AuditorAssignment::create([
            'evaluation_id' => $evaluation->id,
            'auditor_id' => $auditor->id,
            'sequence' => $sequence + 2,
            'status' => 'completed',
            'assigned_at' => now(),
            'accepted_at' => now(),
            'completed_at' => now(),
        ]);
        $auditorEvaluation = AuditorEvaluation::create([
            'evaluation_id' => $evaluation->id,
            'auditor_assignment_id' => $assignment->id,
            'version' => 1,
            'status' => 'submitted',
            'submitted_at' => now(),
            'locked_at' => now(),
            'evidence_sufficiency' => 'sufficient',
            'audience_promise_coherence' => 'coherent',
        ]);
        CriterionResult::create([
            'auditor_evaluation_id' => $auditorEvaluation->id,
            'criterion_id' => $criterionId,
            'assessment' => $score === 70 ? 'partially_meets' : 'meets',
            'score' => $score,
            'rationale' => 'Calibration fixture.',
            'confidence' => 90,
        ]);
    }

    $report = app(ValidationCalibrationService::class)->report()[0];

    expect($report['criterion_agreement_rate'])->toBe(0.6667)
        ->and($report['recurring_disagreements'][0]['criterion'])->toBe('TEST-01')
        ->and($report['recurring_disagreements'][0]['sample_size'])->toBe(1)
        ->and($report['recurring_disagreements'][0]['rate'])->toBeNull();
});

test('only platform administrators can record calibration reviews', function () {
    [$auditorEvaluation] = auditorEvaluationFixture();
    $standardVersion = $auditorEvaluation->evaluation->standardVersion;
    $nonAdmin = User::factory()->create();

    expect(fn () => app(ValidationCalibrationService::class)->recordReview($standardVersion, $nonAdmin))
        ->toThrow(DomainStateTransitionException::class);

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    app(ValidationCalibrationService::class)->recordReview($standardVersion, $admin);

    expect(DB::table('audit_logs')->where('event', 'validation.calibration_reviewed')->count())->toBe(1)
        ->and(ValidationCalibrationPage::canAccess())->toBeFalse();

    actingAs($admin);

    expect(ValidationCalibrationPage::canAccess())->toBeTrue();
});
