<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\ValidationTrustMonitor;

class ValidationTrustMonitorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin()
            || $user->organizations()->wherePivotIn('role', $this->creatorRoles())->exists();
    }

    public function view(User $user, ValidationTrustMonitor $monitor): bool
    {
        return $this->canManage($user, $monitor->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->canManage($user, $organization);
    }

    public function update(User $user, ValidationTrustMonitor $monitor): bool
    {
        return $this->canManage($user, $monitor->organization);
    }

    public function cancel(User $user, ValidationTrustMonitor $monitor): bool
    {
        return $this->canManage($user, $monitor->organization);
    }

    /** @return array<int, string> */
    private function creatorRoles(): array
    {
        return [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
            OrganizationRole::Editor->value,
        ];
    }

    private function canManage(User $user, Organization $organization): bool
    {
        return $user->isPlatformAdmin()
            || $organization->users()
                ->whereKey($user->getKey())
                ->wherePivotIn('role', $this->creatorRoles())
                ->exists();
    }
}
