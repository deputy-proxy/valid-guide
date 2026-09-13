<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertPublicProfileStatus;
use App\Enums\MarketplaceServiceStatus;
use App\Models\MarketplaceService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceServicePolicy
{
    public function view(User $user, MarketplaceService $service): bool
    {
        return $user->isPlatformAdmin()
            || $service->status === MarketplaceServiceStatus::Published
            || $this->owns($user, $service);
    }

    public function create(User $user): bool
    {
        return $this->eligible($user);
    }

    public function update(User $user, MarketplaceService $service): bool
    {
        return $this->owns($user, $service);
    }

    public function publish(User $user, MarketplaceService $service): bool
    {
        return $this->owns($user, $service) && $this->eligible($user);
    }

    public function pause(User $user, MarketplaceService $service): bool
    {
        return $user->isPlatformAdmin() || $this->owns($user, $service);
    }

    public function archive(User $user, MarketplaceService $service): bool
    {
        return $user->isPlatformAdmin() || $this->owns($user, $service);
    }

    public function purchase(User $user, MarketplaceService $service): bool
    {
        return $service->status === MarketplaceServiceStatus::Published
            && ! $this->owns($user, $service);
    }

    private function owns(User $user, MarketplaceService $service): bool
    {
        return $service->auditorProfile()->where('auditor_id', $user->getKey())->exists();
    }

    private function eligible(User $user): bool
    {
        return $user->auditorProfile()
            ->where('status', AuditorProfileStatus::Approved->value)
            ->whereHas('expertBoardMembership', function (Builder $builder): void {
                $builder->where('status', ExpertBoardMembershipStatus::Approved->value);
            })
            ->whereHas('expertPublicProfile', function (Builder $builder): void {
                $builder->where('status', ExpertPublicProfileStatus::Published->value);
            })
            ->exists();
    }
}
