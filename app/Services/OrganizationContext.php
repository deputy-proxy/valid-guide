<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class OrganizationContext
{
    public function resolve(User $user, int|string $organizationId): Organization
    {
        $organization = Organization::query()->find($organizationId);

        if ($organization === null || ! $user->organizations()->whereKey($organization->getKey())->exists()) {
            throw new AuthorizationException('The user is not a member of this organization.');
        }

        return $organization;
    }

    public function current(User $user): Organization
    {
        $organizationId = session('creator.organization_id');

        if (is_int($organizationId) || is_string($organizationId)) {
            return $this->resolve($user, $organizationId);
        }

        $organization = $user->organizations()->orderBy('organizations.name')->first();

        if ($organization === null) {
            throw new AuthorizationException('The user is not a member of an organization.');
        }

        session(['creator.organization_id' => $organization->getKey()]);

        return $organization;
    }

    public function select(User $user, int|string $organizationId): Organization
    {
        $organization = $this->resolve($user, $organizationId);
        session(['creator.organization_id' => $organization->getKey()]);

        return $organization;
    }
}
