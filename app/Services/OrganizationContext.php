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
}
