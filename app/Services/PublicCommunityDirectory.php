<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\CommunityContributionStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Models\CommunityContribution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublicCommunityDirectory
{
    /** @return LengthAwarePaginator<int, CommunityContribution> */
    public function search(?string $query = null): LengthAwarePaginator
    {
        $builder = CommunityContribution::query()
            ->with('auditorProfile.expertPublicProfile')
            ->where('status', CommunityContributionStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value);
            })
            ->whereHas('auditorProfile.expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            });

        $query = $query !== null ? trim($query) : null;
        if ($query !== null && $query !== '') {
            $builder->where(function (Builder $builder) use ($query): void {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('body', 'like', '%'.$query.'%');
            });
        }

        return $builder->latest('published_at')->latest('id')->paginate(12)->withQueryString();
    }

    public function find(string $slug): ?CommunityContribution
    {
        return CommunityContribution::query()
            ->with('auditorProfile.expertPublicProfile')
            ->where('slug', $slug)
            ->where('status', CommunityContributionStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value);
            })
            ->whereHas('auditorProfile.expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            })
            ->first();
    }
}
