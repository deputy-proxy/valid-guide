<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Enums\ProductReleaseStatus;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProductReleaseStateTransition
{
    private const TRANSITIONS = [
        'draft' => [ProductReleaseStatus::Available],
        'available' => [ProductReleaseStatus::Withdrawn, ProductReleaseStatus::Superseded],
        'withdrawn' => [ProductReleaseStatus::Available, ProductReleaseStatus::Superseded],
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

            $organization = $release->product->organization;

            if (! $organization->hasMemberWithRole($actor, OrganizationRole::Owner)
                && ! $organization->hasMemberWithRole($actor, OrganizationRole::Admin)
                && ! $organization->hasMemberWithRole($actor, OrganizationRole::Editor)) {
                throw new DomainStateTransitionException('The actor is not authorized to change this product release state.');
            }

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

            $publishedAt = $release->published_at;

            if ($to === ProductReleaseStatus::Available) {
                if (blank($release->release_identifier) || blank($release->title_snapshot)) {
                    throw new DomainStateTransitionException(
                        'A product release requires a release identifier and title before publication.',
                    );
                }

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
                    'actor_id' => $actor->id,
                ],
                actor: $actor,
            );

            return $release;
        });
    }
}
