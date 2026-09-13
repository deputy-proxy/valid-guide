<?php

use App\Services\ValidationTrustMonitoring;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('valid:monitor-trust', function (ValidationTrustMonitoring $monitoring): int {
    $stats = $monitoring->runDue();

    $this->info(sprintf(
        'Processed %d monitors: %d changed, %d failed, %d recovered, %d skipped.',
        $stats['processed'],
        $stats['changed'],
        $stats['failed'],
        $stats['recovered'],
        $stats['skipped'],
    ));

    return 0;
})->purpose('Check due Validation trust monitors for deterministic post-publication changes.');

Schedule::command('valid:monitor-trust')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
