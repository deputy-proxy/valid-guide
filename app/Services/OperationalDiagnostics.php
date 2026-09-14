<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ValidationTrustMonitorStatus;
use App\Models\ValidationTrustMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OperationalDiagnostics
{
    /**
     * @return array{status:string,timestamp:string,checks:array{database:string,monitoring:array{total:int,due:int,failed:int,invalid:int,stale:int}}}
     */
    public function readiness(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (Throwable $exception) {
            Log::error('application.readiness.failed', [
                'component' => 'database',
                'exception' => $exception::class,
            ]);

            return [
                'status' => 'unready',
                'timestamp' => $now->toIso8601String(),
                'checks' => [
                    'database' => 'failed',
                    'monitoring' => [
                        'total' => 0,
                        'due' => 0,
                        'failed' => 0,
                        'invalid' => 0,
                        'stale' => 0,
                    ],
                ],
            ];
        }

        return [
            'status' => 'ready',
            'timestamp' => $now->toIso8601String(),
            'checks' => [
                'database' => 'ok',
                'monitoring' => $this->monitoringSummary($now),
            ],
        ];
    }

    /**
     * @return array{total:int,due:int,failed:int,invalid:int,stale:int}
     */
    private function monitoringSummary(CarbonImmutable $now): array
    {
        $monitors = ValidationTrustMonitor::query()
            ->get(['id', 'status', 'cadence', 'last_checked_at', 'next_check_at']);

        $summary = [
            'total' => $monitors->count(),
            'due' => 0,
            'failed' => 0,
            'invalid' => 0,
            'stale' => 0,
        ];

        foreach ($monitors as $monitor) {
            if (
                $monitor->next_check_at !== null
                && ! CarbonImmutable::parse((string) $monitor->next_check_at)->isAfter($now)
            ) {
                $summary['due']++;
            }

            if ($monitor->status === ValidationTrustMonitorStatus::Failed) {
                $summary['failed']++;
            }

            if ($monitor->status === ValidationTrustMonitorStatus::Invalid) {
                $summary['invalid']++;
            }

            if (
                $monitor->status === ValidationTrustMonitorStatus::Active
                && (
                    $monitor->last_checked_at === null
                    || CarbonImmutable::parse((string) $monitor->last_checked_at)
                        ->addMinutes($monitor->cadence->intervalMinutes() * 2)
                        ->isBefore($now)
                )
            ) {
                $summary['stale']++;
            }
        }

        return $summary;
    }
}
