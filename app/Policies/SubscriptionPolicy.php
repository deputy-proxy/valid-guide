<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;

final class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin()
            || $user->organizations()->wherePivotIn('role', $this->billingRoles())->exists();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin() || $this->memberWithBillingRole($user, $subscription->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->isPlatformAdmin() || $this->memberWithBillingRole($user, $organization);
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin() || $this->memberWithBillingRole($user, $subscription->organization);
    }

    public function activate(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin();
    }

    public function renew(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin();
    }

    public function failPayment(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin();
    }

    public function recover(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin();
    }

    public function refund(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin();
    }

    public function expire(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformAdmin();
    }

    /** @return list<string> */
    private function billingRoles(): array
    {
        return [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
            OrganizationRole::Billing->value,
        ];
    }

    private function memberWithBillingRole(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->whereKey($user->getKey())
            ->wherePivotIn('role', $this->billingRoles())
            ->exists();
    }
}
