<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertiseArea;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\MarketplaceServiceStatus;
use App\Enums\ProductType;
use App\Models\MarketplaceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class MarketplaceDiscovery
{
    /**
     * @return LengthAwarePaginator<int, MarketplaceService>
     */
    public function search(
        ?string $query = null,
        ?ExpertiseArea $expertise = null,
        ?ProductType $productType = null,
    ): LengthAwarePaginator {
        $builder = MarketplaceService::query()
            ->with('auditorProfile.expertPublicProfile')
            ->where('status', MarketplaceServiceStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value)
                    ->whereHas('expertBoardMembership', function (Builder $builder): void {
                        $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
                    })
                    ->whereHas('expertPublicProfile', function (Builder $builder): void {
                        $builder->where('status', ExpertPublicProfileStatus::Published->value);
                    });
            });

        $query = $query !== null ? trim($query) : null;

        if ($query !== null && $query !== '') {
            $builder->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%');
            });
        }

        if ($expertise !== null) {
            $builder->whereJsonContains('expertise_areas', $expertise->value);
        }

        if ($productType !== null) {
            $builder->whereJsonContains('product_types', $productType->value);
        }

        return $builder
            ->orderBy('title')
            ->orderBy('slug')
            ->paginate(12)
            ->withQueryString();
    }

    public function find(string $slug): ?MarketplaceService
    {
        return MarketplaceService::query()
            ->with('auditorProfile.expertPublicProfile')
            ->where('slug', $slug)
            ->where('status', MarketplaceServiceStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value)
                    ->whereHas('expertBoardMembership', function (Builder $builder): void {
                        $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
                    })
                    ->whereHas('expertPublicProfile', function (Builder $builder): void {
                        $builder->where('status', ExpertPublicProfileStatus::Published->value);
                    });
            })
            ->first();
    }
}
