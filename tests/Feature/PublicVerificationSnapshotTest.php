<?php

declare(strict_types=1);

use App\Enums\AuditorProfileStatus;
use App\Enums\PlatformRole;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\AuditorProfile;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\PublicVerificationRecord;
use App\Models\StandardVersion;
use App\Models\User;
use App\Models\Validation;
use App\Services\DomainStateTransitionException;
use App\Services\PublicVerificationPublication;
use App\Services\ValidationStateTransition;

it('publishes a complete public verification snapshot', function (): void {
    $validation = publicVerificationFixture();

    $record = app(PublicVerificationPublication::class)->publish($validation);
    $snapshot = $record->snapshot;

    expect($record)->toBeInstanceOf(PublicVerificationRecord::class)
        ->and($snapshot)->toBeArray()
        ->and($snapshot['schema_version'])->toBe(1)
        ->and($snapshot)->toHaveKeys([
            'verification',
            'product',
            'standard',
            'scope',
            'result',
            'criteria',
            'findings',
            'strengths',
            'weaknesses',
            'auditors',
            'report',
            'visibility',
        ])
        ->and($snapshot['verification']['identifier'])->toBe('VG-TEST-001')
        ->and($snapshot['product']['release_identifier'])->toBe('release-1')
        ->and($snapshot['standard']['version'])->toBe('1.0')
        ->and($snapshot['criteria'][0]['code'])->toBe('C1')
        ->and($snapshot['criteria'][0]['assessment'])->toBe('meets')
        ->and($snapshot['criteria'][0]['score'])->toBe(80.0)
        ->and($snapshot['findings'][0]['title'])->toBe('Clear strength')
        ->and($snapshot['strengths'])->toHaveCount(1)
        ->and($snapshot['weaknesses'])->toHaveCount(1)
        ->and($snapshot['auditors']['count'])->toBe(1)
        ->and($snapshot['auditors']['disclosed'][0]['name'])->toBe('Public Auditor')
        ->and($snapshot['visibility']['directory'])->toBeTrue()
        ->and($snapshot['visibility']['full_report'])->toBeFalse();
});

it('preserves the public snapshot identity while synchronizing validation trust status', function (): void {
    $validation = publicVerificationFixture();
    $publication = app(PublicVerificationPublication::class);
    $record = $publication->publish($validation);
    $originalSnapshot = $record->snapshot;
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Revoked,
        $admin,
        'Public verification revoked for regression coverage.',
    );

    $snapshot = $record->refresh()->snapshot;

    expect($snapshot['verification']['identifier'])->toBe($originalSnapshot['verification']['identifier'])
        ->and($snapshot['verification']['status'])->toBe('revoked')
        ->and($snapshot['verification']['status_history']['revoked_at'])->not->toBeNull()
        ->and($snapshot['verification']['status_history']['reason'])->toBe('Public verification revoked for regression coverage.')
        ->and($snapshot['product'])->toEqual($originalSnapshot['product'])
        ->and($snapshot['standard'])->toEqual($originalSnapshot['standard']);
});

it('keeps full report visibility inside the persisted public projection', function (): void {
    $validation = publicVerificationFixture();
    $publication = app(PublicVerificationPublication::class);
    $record = $publication->publish($validation);

    $record->update(['full_report_visible' => true]);
    $publication->sync($validation->refresh());

    expect($record->refresh()->snapshot['visibility']['full_report'])->toBeTrue();
});

it('does not create a public record for a revoked validation', function (): void {
    $validation = publicVerificationFixture();
    Validation::query()->whereKey($validation->id)->update([
        'status' => ValidationStatus::Revoked->value,
        'revoked_at' => now(),
        'status_reason' => 'Revoked before publication.',
    ]);
    $validation->refresh();

    expect(fn (): mixed => app(PublicVerificationPublication::class)->publish($validation))
        ->toThrow(DomainStateTransitionException::class)
        ->and(PublicVerificationRecord::query()->count())->toBe(0);
});

function publicVerificationFixture(): Validation
{
    $organization = Organization::create([
        'name' => 'Test Creator',
        'slug' => 'test-creator',
    ]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Test Product',
        'slug' => 'test-product',
        'product_type' => ProductType::Course,
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => 'release-1',
        'title_snapshot' => 'Test Product 1.0',
        'version' => '1.0',
    ]);

    $standard = EvaluationStandard::create([
        'name' => 'Test Standard',
        'slug' => 'test-standard',
    ]);

    $standardVersion = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
    ]);

    $criterion = Criterion::create([
        'standard_version_id' => $standardVersion->id,
        'code' => 'C1',
        'name' => 'Test Criterion',
        'description' => 'A public test criterion.',
        'category' => 'Quality',
        'sequence' => 1,
        'weight' => 1,
        'is_mandatory' => true,
        'voting_mode' => 'individual',
    ]);

    $auditor = User::factory()->create(['name' => 'Public Auditor']);
    AuditorProfile::create([
        'auditor_id' => $auditor->id,
        'status' => AuditorProfileStatus::Approved,
        'bio' => 'Independent evaluator.',
        'credentials' => ['certification' => 'Certified Reviewer'],
    ]);

    $request = EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'complexity' => 'standard',
    ]);

    $evaluation = Evaluation::create([
        'evaluation_request_id' => $request->id,
        'product_release_id' => $release->id,
        'standard_version_id' => $standardVersion->id,
        'status' => 'completed',
        'decision' => 'valid',
        'overall_score' => 80,
        'decision_rationale' => 'The product meets the public standard.',
    ]);

    $assignment = AuditorAssignment::create([
        'evaluation_id' => $evaluation->id,
        'auditor_id' => $auditor->id,
        'sequence' => 1,
        'status' => 'completed',
    ]);

    $auditorEvaluation = AuditorEvaluation::create([
        'evaluation_id' => $evaluation->id,
        'auditor_assignment_id' => $assignment->id,
        'version' => 1,
        'status' => 'submitted',
    ]);

    CriterionResult::create([
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'criterion_id' => $criterion->id,
        'assessment' => 'meets',
        'score' => 80,
        'rationale' => 'Sufficient evidence.',
        'confidence' => 90,
    ]);

    Finding::create([
        'evaluation_id' => $evaluation->id,
        'criterion_id' => $criterion->id,
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'type' => 'strength',
        'title' => 'Clear strength',
        'description' => 'The criterion is well supported.',
    ]);

    Finding::create([
        'evaluation_id' => $evaluation->id,
        'criterion_id' => $criterion->id,
        'auditor_evaluation_id' => $auditorEvaluation->id,
        'type' => 'weakness',
        'severity' => 'minor',
        'title' => 'Minor weakness',
        'description' => 'Some evidence could be clearer.',
    ]);

    return Validation::create([
        'product_release_id' => $release->id,
        'evaluation_id' => $evaluation->id,
        'verification_identifier' => 'VG-TEST-001',
        'issued_at' => now(),
        'status' => ValidationStatus::Active,
    ]);
}
