<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Enums\ProductReleaseStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;

class ProductReleasePolicy
{
    public function view(User $user, ProductRelease $release): bool
    {
        return $this->canManageCreatorResource($user, $release->product->organization);
    }

    public function create(User $user, ?Product $product = null): bool
    {
        if ($product instanceof Product) {
            return $this->canManageCreatorResource($user, $product->organization);
        }

        return $user->organizations()
            ->wherePivotIn('role', $this->creatorRoles())
            ->exists();
    }

    public function update(User $user, ProductRelease $release): bool
    {
        return $release->status === ProductReleaseStatus::Draft
            && $this->canManageCreatorResource($user, $release->product->organization);
    }

    public function publish(User $user, ProductRelease $release): bool
    {
        return $release->status === ProductReleaseStatus::Draft
            && $this->canManageCreatorResource($user, $release->product->organization);
    }

    public function supersede(User $user, ProductRelease $release): bool
    {
        return $release->status === ProductReleaseStatus::Current
            && $this->canManageCreatorResource($user, $release->product->organization);
    }

    public function withdraw(User $user, ProductRelease $release): bool
    {
        return $release->status === ProductReleaseStatus::Current
            && $this->canManageCreatorResource($user, $release->product->organization);
    }

    public function delete(User $user, ProductRelease $release): bool
    {
        return $release->status === ProductReleaseStatus::Draft
            && $this->canManageCreatorResource($user, $release->product->organization);
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
}
