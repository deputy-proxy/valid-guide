<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\CommunityContributionStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertPublicProfileStatus;
use App\Models\CommunityContribution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublicCommunityDirectory
{
    /** @return LengthAwarePaginator<int, CommunityContribution> */
    public function search(?string $query = null): LengthAwarePaginator
    {
        $builder = $this->publishedQuery()
            ->when($query !== null && trim($query) !== '', function (Builder $builder) use ($query): void {
                $query = trim((string) $query);
                $builder->where(function (Builder $builder) use ($query): void {
                    $builder->where('title', 'like', '%'.$query.'%')
                        ->orWhere('body', 'like', '%'.$query.'%');
                });
            });

        return $builder->latest('published_at')->latest('id')->paginate(12)->withQueryString();
    }

    public function find(string $slug): ?CommunityContribution
    {
        return $this->publishedQuery()->where('slug', $slug)->first();
    }

    /** @return Builder<CommunityContribution> */
    private function publishedQuery(): Builder
    {
        return CommunityContribution::query()
            ->with('auditorProfile.expertPublicProfile')
            ->where('status', CommunityContributionStatus::Published->value)
            ->whereHas('auditorProfile', function (Builder $builder): void {
                $builder->where('status', AuditorProfileStatus::Approved->value);
            })
            ->whereHas('auditorProfile.expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            })
            ->whereHas('auditorProfile.expertPublicProfile', function (Builder $builder): void {
                $builder->where('status', ExpertPublicProfileStatus::Published->value);
            });
    }
}
