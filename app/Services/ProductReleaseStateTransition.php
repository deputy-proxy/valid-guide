<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductReleaseStatus;
use App\Models\ProductRelease;
use Illuminate\Support\Facades\DB;

final class ProductReleaseStateTransition
{
    private const TRANSITIONS = [
        'draft' => [ProductReleaseStatus::Available],
        'available' => [ProductReleaseStatus::Withdrawn, ProductReleaseStatus::Superseded],
        'withdrawn' => [ProductReleaseStatus::Available, ProductReleaseStatus::Superseded],
        'superseded' => [],
    ];

    public function transition(ProductRelease $release, ProductReleaseStatus $to): ProductRelease
    {
        return DB::transaction(function () use ($release, $to): ProductRelease {
            $release = ProductRelease::query()->whereKey($release->getKey())->lockForUpdate()->firstOrFail();
            $from = $release->status instanceof ProductReleaseStatus
                ? $release->status->value
                : (string) $release->status;

            if ($from === $to->value) {
                throw new DomainStateTransitionException('A product release cannot transition to its current state.');
            }

            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new DomainStateTransitionException(
                    sprintf('Product release cannot transition from [%s] to [%s].', $from, $to->value),
                );
            }

            if ($to === ProductReleaseStatus::Available && blank($release->published_at)) {
                $release->published_at = now();
            }

            $before = ['status' => $from, 'published_at' => $release->published_at?->toIso8601String()];
            $release->status = $to;
            $release->save();

            AuditLogger::record(
                event: 'product_release.status_changed',
                auditable: $release,
                before: $before,
                after: [
                    'status' => $to->value,
                    'published_at' => $release->published_at?->toIso8601String(),
                ],
            );

            return $release->refresh();
        });
    }
}
