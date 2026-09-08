<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;

class ProductReleasePolicy
{
    public function view(User $user, ProductRelease $release): bool
    {
        return $release->product->organization->users()->whereKey($user->getKey())->exists();
    }

    public function create(User $user, Product $product): bool
    {
        return $product->organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $product->organization->hasMemberWithRole($user, OrganizationRole::Admin)
            || $product->organization->hasMemberWithRole($user, OrganizationRole::Editor);
    }

    public function update(User $user, ProductRelease $release): bool
    {
        return $release->product->organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $release->product->organization->hasMemberWithRole($user, OrganizationRole::Admin)
            || $release->product->organization->hasMemberWithRole($user, OrganizationRole::Editor);
    }

    public function delete(User $user, ProductRelease $release): bool
    {
        return $release->product->organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $release->product->organization->hasMemberWithRole($user, OrganizationRole::Admin);
    }
}
