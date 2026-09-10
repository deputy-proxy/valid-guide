<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductReleaseStatus;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ProductReleaseStateTransition
{
    private const TRANSITIONS = [
        'draft' => ['current'],
        'current' => ['withdrawn', 'superseded'],
        'withdrawn' => [],
        'superseded' => [],
    ];

    public function transition(ProductRelease $release, ProductReleaseStatus $to, User $actor): ProductRelease
    {
        return DB::transaction(function () use ($release, $to, $actor): ProductRelease {
            $release = ProductRelease::query()
                ->with('product.organization')
                ->whereKey($release->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize($this->ability($to), $release);

            $from = $release->status->value;

            if ($from === $to->value) {
                throw new DomainStateTransitionException(
                    'A product release cannot transition to its current state.',
                );
            }

            if (! in_array($to->value, self::TRANSITIONS[$from], true)) {
                throw new DomainStateTransitionException(
                    sprintf('Product release cannot transition from [%s] to [%s].', $from, $to->value),
                );
            }

            if ($to === ProductReleaseStatus::Current) {
                if (blank($release->release_identifier) || blank($release->title_snapshot)) {
                    throw new DomainStateTransitionException(
                        'A product release requires a release identifier and title before publication.',
                    );
                }

                $existingCurrent = ProductRelease::query()
                    ->where('product_id', $release->product_id)
                    ->where('status', ProductReleaseStatus::Current->value)
                    ->whereKeyNot($release->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existingCurrent !== null) {
                    $previous = [
                        'status' => $existingCurrent->status->value,
                        'published_at' => $existingCurrent->published_at?->toIso8601String(),
                    ];

                    ProductRelease::query()
                        ->whereKey($existingCurrent->getKey())
                        ->update([
                            'status' => ProductReleaseStatus::Superseded->value,
                            'updated_at' => now(),
                        ]);

                    $existingCurrent->refresh();

                    AuditLogger::record(
                        event: 'product_release.status_changed',
                        auditable: $existingCurrent,
                        before: $previous,
                        after: [
                            'status' => ProductReleaseStatus::Superseded->value,
                            'published_at' => $existingCurrent->published_at?->toIso8601String(),
                            'actor_id' => $actor->getKey(),
                        ],
                    );
                }
            }

            $publishedAt = $release->published_at;
            if ($to === ProductReleaseStatus::Current) {
                $publishedAt ??= now();
            }

            $before = [
                'status' => $from,
                'published_at' => $release->published_at?->toIso8601String(),
            ];

            ProductRelease::query()
                ->whereKey($release->getKey())
                ->update([
                    'status' => $to->value,
                    'published_at' => $publishedAt,
                    'updated_at' => now(),
                ]);

            $release->refresh();

            AuditLogger::record(
                event: 'product_release.status_changed',
                auditable: $release,
                before: $before,
                after: [
                    'status' => $to->value,
                    'published_at' => $release->published_at?->toIso8601String(),
                    'actor_id' => $actor->getKey(),
                ],
            );

            return $release;
        });
    }

    public function publish(ProductRelease $release, User $actor): ProductRelease
    {
        return $this->transition($release, ProductReleaseStatus::Current, $actor);
    }

    public function supersede(ProductRelease $release, User $actor): ProductRelease
    {
        return $this->transition($release, ProductReleaseStatus::Superseded, $actor);
    }

    public function withdraw(ProductRelease $release, User $actor): ProductRelease
    {
        return $this->transition($release, ProductReleaseStatus::Withdrawn, $actor);
    }

    private function ability(ProductReleaseStatus $to): string
    {
        return match ($to) {
            ProductReleaseStatus::Current => 'publish',
            ProductReleaseStatus::Superseded => 'supersede',
            ProductReleaseStatus::Withdrawn => 'withdraw',
            ProductReleaseStatus::Draft => 'update',
        };
    }
}
