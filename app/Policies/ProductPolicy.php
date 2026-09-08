<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organizations()->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $product->organization->users()->whereKey($user->getKey())->exists();
    }

    public function create(User $user, Organization $organization): bool
    {
        return $organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $organization->hasMemberWithRole($user, OrganizationRole::Admin)
            || $organization->hasMemberWithRole($user, OrganizationRole::Editor);
    }

    public function update(User $user, Product $product): bool
    {
        return $product->organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $product->organization->hasMemberWithRole($user, OrganizationRole::Admin)
            || $product->organization->hasMemberWithRole($user, OrganizationRole::Editor);
    }

    public function delete(User $user, Product $product): bool
    {
        return $product->organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $product->organization->hasMemberWithRole($user, OrganizationRole::Admin);
    }
}
