<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertiseArea;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\ProductType;
use App\Models\ExpertPublicProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublicExpertDirectory
{
    /**
     * @return LengthAwarePaginator<int, ExpertPublicProfile>
     */
    public function search(
        ?string $query = null,
        ?ExpertiseArea $expertise = null,
        ?ProductType $productType = null,
    ): LengthAwarePaginator {
        $builder = ExpertPublicProfile::query()
            ->with('auditorProfile.expertBoardMembership')
            ->where('status', ExpertPublicProfileStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value);
            })
            ->whereHas('auditorProfile.expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            });

        $query = $query !== null ? trim($query) : null;

        if ($query !== null && $query !== '') {
            $builder->where(function (Builder $builder) use ($query): void {
                $builder->where('display_name', 'like', '%'.$query.'%')
                    ->orWhere('bio', 'like', '%'.$query.'%')
                    ->orWhere('credentials', 'like', '%'.$query.'%');
            });
        }

        if ($expertise !== null) {
            $builder->whereJsonContains('expertise_areas', $expertise->value);
        }

        if ($productType !== null) {
            $builder->whereJsonContains('product_types', $productType->value);
        }

        return $builder
            ->orderBy('display_name')
            ->orderBy('slug')
            ->paginate(12)
            ->withQueryString();
    }

    public function find(string $slug): ?ExpertPublicProfile
    {
        return ExpertPublicProfile::query()
            ->with('auditorProfile.expertBoardMembership')
            ->where('slug', $slug)
            ->where('status', ExpertPublicProfileStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value);
            })
            ->whereHas('auditorProfile.expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            })
            ->first();
    }
}
