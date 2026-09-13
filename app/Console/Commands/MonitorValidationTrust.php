<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ValidationTrustMonitoring;
use Illuminate\Console\Command;

class MonitorValidationTrust extends Command
{
    protected $signature = 'valid:monitor-trust';

    protected $description = 'Check due Validation trust monitors for deterministic post-publication changes.';

    public function handle(ValidationTrustMonitoring $monitoring): int
    {
        $stats = $monitoring->runDue();

        $this->info(sprintf(
            'Processed %d monitors: %d changed, %d failed, %d recovered, %d skipped.',
            $stats['processed'],
            $stats['changed'],
            $stats['failed'],
            $stats['recovered'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
