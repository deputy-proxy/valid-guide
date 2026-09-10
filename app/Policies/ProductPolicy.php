<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Enums\ProductStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organizations()
            ->wherePivotIn('role', $this->creatorRoles())
            ->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->canManageCreatorResource($user, $product->organization);
    }

    public function create(User $user): bool
    {
        return $user->organizations()
            ->wherePivotIn('role', $this->creatorRoles())
            ->exists();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->canManageCreatorResource($user, $product->organization)
            && $product->status !== ProductStatus::Archived;
    }

    public function archive(User $user, Product $product): bool
    {
        return $this->canManageCreatorResource($user, $product->organization)
            && $product->status !== ProductStatus::Archived;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->canManageOrganizationResource($user, $product->organization);
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

    private function canManageCreatorResource(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->whereKey($user->getKey())
            ->wherePivotIn('role', $this->creatorRoles())
            ->exists();
    }

    private function canManageOrganizationResource(User $user, Organization $organization): bool
    {
        return $organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $organization->hasMemberWithRole($user, OrganizationRole::Admin);
    }
}
