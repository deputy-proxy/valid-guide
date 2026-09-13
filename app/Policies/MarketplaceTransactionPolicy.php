<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MarketplaceTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceTransactionPolicy
{
    public function view(User $user, MarketplaceTransaction $transaction): bool
    {
        return $user->isPlatformAdmin()
            || $transaction->buyer_id === $user->getKey()
            || $transaction->auditorProfile()->where('auditor_id', $user->getKey())->exists()
            || ($transaction->organization_id !== null && $transaction->organization()->whereHas('users', function (Builder $builder) use ($user): void {
                $builder->whereKey($user->getKey());
            })->exists());
    }

    public function pay(User $user, MarketplaceTransaction $transaction): bool
    {
        return $transaction->buyer_id === $user->getKey();
    }

    public function start(User $user, MarketplaceTransaction $transaction): bool
    {
        return $transaction->auditorProfile()->where('auditor_id', $user->getKey())->exists();
    }

    public function complete(User $user, MarketplaceTransaction $transaction): bool
    {
        return $transaction->auditorProfile()->where('auditor_id', $user->getKey())->exists();
    }

    public function cancel(User $user, MarketplaceTransaction $transaction): bool
    {
        return $transaction->buyer_id === $user->getKey()
            || $transaction->auditorProfile()->where('auditor_id', $user->getKey())->exists();
    }

    public function refund(User $user, MarketplaceTransaction $transaction): bool
    {
        return $user->isPlatformAdmin();
    }
}
