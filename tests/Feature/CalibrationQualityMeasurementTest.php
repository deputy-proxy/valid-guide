<?php

declare(strict_types=1);

use App\Enums\AudiencePromiseCoherence;
use App\Enums\CriterionVotingMode;
use App\Enums\EvidenceSufficiency;
use App\Enums\PlatformRole;
use App\Filament\Pages\CalibrationQualityPage;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\ProductRelease;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\CalibrationQualityMeasurement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<int, float|null>  $scores
 */
function completedCalibrationEvaluation(
    StandardVersion $version,
    EvaluationRequest $request,
    ProductRelease $release,
    int $evaluationNumber,
    array $scores,
): Evaluation {
    $evaluation = Evaluation::create([
        'evaluation_request_id' => $request->id,
        'product_release_id' => $release->id,
        'standard_version_id' => $version->id,
        'status' => 'completed',
        'decision' => $evaluationNumber === 2 ? 'not_validated' : 'validated',
        'overall_score' => 80,
        'completed_at' => now(),
    ]);

    $criterion = Criterion::query()->where('standard_version_id', $version->id)->firstOrFail();

    foreach ($scores as $sequence => $score) {
        $auditor = User::factory()->create();
        $assignment = AuditorAssignment::create([
            'evaluation_id' => $evaluation->id,
            'auditor_id' => $auditor->id,
            'sequence' => $sequence + 1,
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
            'evidence_sufficiency' => EvidenceSufficiency::Sufficient,
            'audience_promise_coherence' => AudiencePromiseCoherence::Coherent,
        ]);
        CriterionResult::create([
            'auditor_evaluation_id' => $auditorEvaluation->id,
            'criterion_id' => $criterion->id,
            'assessment' => $score === null ? 'insufficient_evidence' : ($score >= 75 ? 'meets' : 'partially_meets'),
            'score' => $score,
            'rationale' => 'Calibration test rationale.',
            'confidence' => 90,
            'submitted_at' => now(),
        ]);
    }

    return $evaluation;
}

test('calibration aggregates completed evaluations per standard version without exposing restricted material', function () {
    [$seedAuditorEvaluation] = auditorEvaluationFixture(CriterionVotingMode::Individual);
    $version = $seedAuditorEvaluation->evaluation->standardVersion;
    $request = $seedAuditorEvaluation->evaluation->request;
    $release = $seedAuditorEvaluation->evaluation->productRelease;
    $seedAuditorEvaluation->evaluation->delete();

    completedCalibrationEvaluation($version, $request, $release, 1, [80, 70]);
    completedCalibrationEvaluation($version, $request, $release, 2, [80, 70]);
    completedCalibrationEvaluation($version, $request, $release, 3, [null, 80]);

    $evaluationCount = Evaluation::count();
    $criterionResultCount = CriterionResult::count();
    $metrics = app(CalibrationQualityMeasurement::class)->forStandardVersion($version);

    expect($metrics['completed_evaluations'])->toBe(3)
        ->and($metrics['locked_auditor_evaluations'])->toBe(6)
        ->and($metrics['criterion_results'])->toBe(6)
        ->and($metrics['insufficient_evidence'])->toBe(1)
        ->and($metrics['insufficient_evidence_rate'])->toBe(16.67)
        ->and($metrics['comparable_criterion_groups'])->toBe(3)
        ->and($metrics['agreement_rate'])->toBe(33.33)
        ->and($metrics['score_variance'])->toBe(16.6667)
        ->and($metrics['recurring_disagreements'])->toHaveCount(1)
        ->and($metrics['recurring_disagreements'][0]['disagreements'])->toBe(2)
        ->and($metrics['status'])->toBe('sufficient')
        ->and(Evaluation::count())->toBe($evaluationCount)
        ->and(CriterionResult::count())->toBe($criterionResultCount)
        ->and(json_encode($metrics))->not->toContain('Calibration test rationale');
});

test('calibration distinguishes methodology versions and does not mix their samples', function () {
    [$firstAuditorEvaluation] = auditorEvaluationFixture();
    $firstVersion = $firstAuditorEvaluation->evaluation->standardVersion;
    $firstRequest = $firstAuditorEvaluation->evaluation->request;
    $firstRelease = $firstAuditorEvaluation->evaluation->productRelease;
    $firstAuditorEvaluation->evaluation->delete();
    completedCalibrationEvaluation($firstVersion, $firstRequest, $firstRelease, 1, [80, 80]);
    completedCalibrationEvaluation($firstVersion, $firstRequest, $firstRelease, 2, [80, 80]);
    completedCalibrationEvaluation($firstVersion, $firstRequest, $firstRelease, 3, [80, 80]);

    [$secondAuditorEvaluation] = auditorEvaluationFixture();
    $secondVersion = $secondAuditorEvaluation->evaluation->standardVersion;
    $secondRequest = $secondAuditorEvaluation->evaluation->request;
    $secondRelease = $secondAuditorEvaluation->evaluation->productRelease;
    $secondAuditorEvaluation->evaluation->delete();
    completedCalibrationEvaluation($secondVersion, $secondRequest, $secondRelease, 1, [70, 70]);

    $firstMetrics = app(CalibrationQualityMeasurement::class)->forStandardVersion($firstVersion);
    $secondMetrics = app(CalibrationQualityMeasurement::class)->forStandardVersion($secondVersion);

    expect($firstVersion->id)->not->toBe($secondVersion->id)
        ->and($firstMetrics['completed_evaluations'])->toBe(3)
        ->and($secondMetrics['completed_evaluations'])->toBe(1)
        ->and($firstMetrics['status'])->toBe('sufficient')
        ->and($secondMetrics['status'])->toBe('insufficient_data');
});

test('calibration returns insufficient data and flags anomalies instead of manufacturing rates', function () {
    [$seedAuditorEvaluation] = auditorEvaluationFixture();
    $version = $seedAuditorEvaluation->evaluation->standardVersion;
    $request = $seedAuditorEvaluation->evaluation->request;
    $release = $seedAuditorEvaluation->evaluation->productRelease;
    $seedAuditorEvaluation->evaluation->delete();

    $evaluation = completedCalibrationEvaluation($version, $request, $release, 1, [80, 80]);
    DB::table('criterion_results')
        ->where('auditor_evaluation_id', $evaluation->auditorEvaluations()->firstOrFail()->id)
        ->update(['score' => null]);

    $metrics = app(CalibrationQualityMeasurement::class)->forStandardVersion($version);

    expect($metrics['status'])->toBe('anomalous_data')
        ->and($metrics['agreement_rate'])->toBeNull()
        ->and($metrics['anomalous_results'])->toBe(1)
        ->and($metrics['review_flags'])->toContain('insufficient_sample')
        ->and($metrics['review_flags'])->toContain('anomalous_data');
});

test('only platform administrators can access and record calibration reviews', function () {
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    $organizationUser = User::factory()->create();
    [$auditorEvaluation] = auditorEvaluationFixture();
    $version = $auditorEvaluation->evaluation->standardVersion;

    expect(CalibrationQualityPage::canAccess())->toBeFalse();

    $this->actingAs($admin);
    expect(CalibrationQualityPage::canAccess())->toBeTrue();

    $auditLog = app(CalibrationQualityMeasurement::class)->recordReview($version, $admin);

    expect($auditLog->event)->toBe('calibration.reviewed')
        ->and(DB::table('audit_logs')->where('event', 'calibration.reviewed')->count())->toBe(1);

    expect(fn () => app(CalibrationQualityMeasurement::class)->recordReview($version, $organizationUser))
        ->toThrow(AuthorizationException::class);
});

test('calibration page is denied to non-admin users at the HTTP boundary', function () {
    $organizationUser = User::factory()->create();

    $this->actingAs($organizationUser)
        ->get('/admin/calibration')
        ->assertForbidden();
});
