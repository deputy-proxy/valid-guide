<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps the Phase 3 integration audit matrix complete', function (): void {
    $path = base_path('docs/phase-3-integration-audit.md');

    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);

    foreach ([
        'Auditor workspace and assignments',
        'Auditor eligibility and COI',
        'Assessment, evidence and findings',
        'Auditor submission',
        'Platform governance',
        'Reports and creator visibility',
        'Guidance and creator progress',
        'Matching and suitability',
        'Public discovery',
        'Explainable recommendations',
        'Notifications and action queues',
        'Cross-cutting authorization',
        'UI and Livewire transport',
    ] as $area) {
        expect($content)->toContain('| '.$area.' |');
    }

    foreach ([
        'Authorization is enforced server-side.',
        'Organization-scoped data cannot be accessed through another tenant\'s identifiers.',
        'Frozen Standard Versions remain the source of truth for an active Evaluation.',
        'Submitted Auditor work and published reports retain their historical meaning.',
        'Notifications and action queues are derived from authoritative state',
        'Direct requests and Livewire payloads must not permit mutation',
        'Accessibility-critical landmarks and keyboard/focus affordances remain present',
        'Public discovery and recommendation queries verify the authoritative Validation, Product Release and Product state',
        'An active Validation attached to a superseded or withdrawn Product Release is not eligible',
        'Commercial product fields, payment state and affiliate relationships do not contribute',
        'Matching and recommendation explanations are derived only from explicit public suitability and trust signals',
    ] as $invariant) {
        expect($content)->toContain($invariant);
    }
});

it('keeps every Phase 3 regression matrix reference backed by an existing feature test', function (): void {
    $files = [
        'AuditorWorkspaceTest.php',
        'AuditorEvaluationWorkspaceTest.php',
        'AuditorEligibilityTest.php',
        'AuditorAnnualConflictDeclarationTest.php',
        'AuditorConflictDeclarationTest.php',
        'AuditorEvidenceAndFindingTest.php',
        'CriterionApplicabilityTest.php',
        'CriterionVotingTest.php',
        'AuditorEvaluationSubmissionTest.php',
        'AuditorEvaluationFinalizationTest.php',
        'PlatformGovernanceInterfaceTest.php',
        'EvaluationDecisionServiceTest.php',
        'ValidationIssuanceTest.php',
        'ValidationStateTransitionTest.php',
        'CreatorReportAccessTest.php',
        'ReportDeliveryTest.php',
        'ReportVersioningTest.php',
        'PublicVerificationTest.php',
        'PublicVerificationSnapshotTest.php',
        'ImprovementGuidanceWorkflowTest.php',
        'ImprovementOpportunityWorkflowTest.php',
        'CreatorImprovementOpportunityIntegrationTest.php',
        'ProductMatchingTest.php',
        'ProductSuitabilityTest.php',
        'PublicDirectoryTest.php',
        'ProductRecommendationsTest.php',
        'WorkflowNotificationTest.php',
        'CreatorActionWorkflowTest.php',
        'AuthorizationTest.php',
        'AuditLogTest.php',
        'Phase2UiAuditTest.php',
    ];

    foreach ($files as $file) {
        expect(File::exists(base_path('tests/Feature/'.$file)))->toBeTrue($file);
    }
});

it('keeps raw bulk mutations outside application workflows', function (): void {
    $files = File::allFiles(base_path('app'));
    $violations = [];

    foreach ($files as $file) {
        $relativePath = $file->getRelativePathname();

        if (str_starts_with($relativePath, 'Services'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $content = $file->getContents();

        if (preg_match('/DB::(?:table|query)\s*\([^;]*?\)\s*->\s*(?:update|delete)\s*\(/s', $content) === 1) {
            $violations[] = $relativePath;
        }

        if (preg_match('/::query\s*\(\s*\)\s*(?:->[^;]+?)*?->\s*(?:update|delete)\s*\(/s', $content) === 1) {
            $violations[] = $relativePath;
        }
    }

    expect($violations)->toBe([]);
});

it('keeps representative public accessibility landmarks intact', function (): void {
    $response = $this->get(route('public.verify'));

    $response->assertOk()
        ->assertSee('<main id="main-content"', false)
        ->assertSee('<nav aria-label="Primary navigation">', false)
        ->assertSee('<nav aria-label="Footer navigation">', false)
        ->assertSee('href="#main-content"', false)
        ->assertSee('focus:ring-2', false);

    $html = $response->getContent();

    expect(substr_count($html, '<main '))->toBe(1)
        ->and(substr_count($html, 'aria-label="Primary navigation"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="Footer navigation"'))->toBe(1);
});
