<?php

use App\Services\ValidationTrustMonitoring;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('valid:monitor-trust', function (ValidationTrustMonitoring $monitoring): int {
    $runId = (string) Str::uuid();
    Log::withContext([
        'operation' => 'validation_trust_monitor',
        'run_id' => $runId,
    ]);

    Log::info('validation_trust_monitor.run_started');
    $stats = $monitoring->runDue();

    Log::info('validation_trust_monitor.run_completed', $stats);

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
