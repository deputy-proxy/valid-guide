<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('documents the complete phase 1 invariant audit matrix', function (): void {
    $path = base_path('docs/phase-1-invariant-audit.md');

    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);

    foreach ([
        'Methodology',
        'Assessment',
        'Decision',
        'Voting',
        'Staffing',
        'Auditor',
        'Evaluation',
        'Report',
        'Refund',
        'Finance',
        'Public trust',
        'Security',
        'Data integrity',
        'Concurrency',
    ] as $area) {
        expect($content)->toContain('| '.$area.' |');
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
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/DB::(?:table|query)\([^;]*\)->(?:update|delete)\s*\(/', $line) === 1) {
                $violations[] = sprintf('%s:%d', $relativePath, $lineNumber + 1);
            }
        }
    }

    expect($violations)->toBe([]);
});
