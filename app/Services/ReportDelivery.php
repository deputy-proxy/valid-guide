<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportDelivery
{
    public function deliver(Report $report, User $actor): Report
    {
        if (! $actor->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can record report delivery.');
        }

        return DB::transaction(function () use ($report): Report {
            $report = Report::query()->lockForUpdate()->findOrFail($report->getKey());

            if ($report->delivered_at !== null) {
                throw new DomainStateTransitionException('The report has already been delivered.');
            }

            if ($report->current_version_id === null) {
                throw new DomainStateTransitionException('A report must have a current version before it can be delivered.');
            }

            $deliveredAt = now();

            Report::query()
                ->whereKey($report->getKey())
                ->update([
                    'delivered_at' => $deliveredAt,
                    'updated_at' => $deliveredAt,
                ]);

            $report->refresh();

            AuditLogger::record(
                event: 'report.delivered',
                auditable: $report,
                after: [
                    'delivered_at' => $report->delivered_at?->toISOString(),
                    'report_version_id' => $report->current_version_id,
                ],
            );

            return $report;
        });
    }
}
