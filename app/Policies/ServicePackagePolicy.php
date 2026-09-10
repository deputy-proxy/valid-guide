<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServicePackage;
use App\Models\User;

class ServicePackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, ServicePackage $package): bool
    {
        return $user->isPlatformAdmin() || $package->status === 'active';
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, ServicePackage $package): bool
    {
        return $user->isPlatformAdmin();
    }

    public function archive(User $user, ServicePackage $package): bool
    {
        return $user->isPlatformAdmin() && $package->status === 'active';
    }
}
