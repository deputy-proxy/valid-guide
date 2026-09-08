<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $organization->users()->whereKey($user->getKey())->exists();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $organization->hasMemberWithRole($user, OrganizationRole::Admin);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $organization->hasMemberWithRole($user, OrganizationRole::Admin);
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $organization->hasMemberWithRole($user, OrganizationRole::Admin)
            || $organization->hasMemberWithRole($user, OrganizationRole::Billing);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $organization->hasMemberWithRole($user, OrganizationRole::Owner);
    }
}
