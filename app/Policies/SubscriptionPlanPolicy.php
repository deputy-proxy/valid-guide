<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubscriptionPlan;
use App\Models\User;

final class SubscriptionPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        return $user->isPlatformAdmin();
    }
}
