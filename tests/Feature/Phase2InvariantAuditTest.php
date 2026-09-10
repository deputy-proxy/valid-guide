<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('documents the complete phase 2 invariant audit matrix', function (): void {
    $path = base_path('docs/phase-2-invariant-audit.md');

    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);

    foreach ([
        'Tenancy',
        'Authorization',
        'Product',
        'Product Release',
        'Evaluation Request',
        'Commercial snapshot',
        'Price integrity',
        'Payment',
        'Stripe',
        'Refund',
        'Submitted Material',
        'Privacy',
        'Dashboard',
        'Wizard',
        'Raw/bulk writes',
        'Historical integrity',
        'Concurrency',
        'Quality',
    ] as $area) {
        expect($content)->toContain('| '.$area.' |');
    }
});

it('keeps every phase 2 audit reference backed by an existing feature test', function (): void {
    $files = [
        'AuthorizationTest.php',
        'CreatorDashboardTest.php',
        'CreatorEvaluationRequestWizardTest.php',
        'CreatorRefundServiceTest.php',
        'ProductManagementTest.php',
        'ProductReleaseLifecycleTest.php',
        'EvaluationRequestLifecycleIntegrityTest.php',
        'EvaluationRequestCommercialTermsTest.php',
        'EvaluationQuoteServiceTest.php',
        'EvaluationMaterialIntakeTest.php',
        'ReportDeliveryTest.php',
        'StripePaymentFlowTest.php',
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
