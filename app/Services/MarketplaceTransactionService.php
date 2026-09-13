<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MarketplaceServiceStatus;
use App\Enums\MarketplaceTransactionStatus;
use App\Enums\OrganizationRole;
use App\Models\MarketplaceService;
use App\Models\MarketplaceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class MarketplaceTransactionService
{
    public function create(User $buyer, MarketplaceService $service, ?int $organizationId = null): MarketplaceTransaction
    {
        Gate::forUser($buyer)->authorize('purchase', $service);

        return DB::transaction(function () use ($buyer, $service, $organizationId): MarketplaceTransaction {
            /** @var MarketplaceService $service */
            $service = MarketplaceService::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($service->status !== MarketplaceServiceStatus::Published) {
                throw new DomainStateTransitionException('Only published marketplace services can be purchased.');
            }

            if ($organizationId !== null) {
                $organization = $buyer->organizations()->whereKey($organizationId)->first();
                if ($organization === null) {
                    throw new DomainStateTransitionException('The buyer is not a member of the selected organization.');
                }

                $canPurchase = $organization->hasMemberWithRole($buyer, OrganizationRole::Owner)
                    || $organization->hasMemberWithRole($buyer, OrganizationRole::Admin)
                    || $organization->hasMemberWithRole($buyer, OrganizationRole::Editor)
                    || $organization->hasMemberWithRole($buyer, OrganizationRole::Billing);

                if ($canPurchase === false) {
                    throw new DomainStateTransitionException('The buyer is not authorized to create a marketplace transaction for the organization.');
                }
            }

            $transaction = MarketplaceTransaction::query()->create([
                'marketplace_service_id' => $service->getKey(),
                'auditor_profile_id' => $service->auditor_profile_id,
                'buyer_id' => $buyer->getKey(),
                'organization_id' => $organizationId,
                'amount_minor' => $service->price_minor,
                'currency' => strtoupper($service->currency),
                'status' => MarketplaceTransactionStatus::Pending,
            ]);

            AuditLogger::record(
                event: 'marketplace_transaction.created',
                auditable: $transaction,
                after: [
                    'status' => MarketplaceTransactionStatus::Pending->value,
                    'amount_minor' => $transaction->amount_minor,
                    'currency' => $transaction->currency,
                ],
                actor: $buyer,
            );

            return $transaction->refresh();
        });
    }

    public function markPaid(MarketplaceTransaction $transaction, User $actor, ?string $provider = null, ?string $providerPaymentId = null): MarketplaceTransaction
    {
        Gate::forUser($actor)->authorize('pay', $transaction);

        return $this->transition($transaction, $actor, MarketplaceTransactionStatus::Paid, [MarketplaceTransactionStatus::Pending], [
            'paid_at' => now(),
            'provider' => $provider,
            'provider_payment_id' => $providerPaymentId,
        ], 'marketplace_transaction.paid');
    }

    public function start(MarketplaceTransaction $transaction, User $actor): MarketplaceTransaction
    {
        Gate::forUser($actor)->authorize('start', $transaction);

        return $this->transition($transaction, $actor, MarketplaceTransactionStatus::InProgress, [MarketplaceTransactionStatus::Paid], ['started_at' => now()], 'marketplace_transaction.started');
    }

    public function complete(MarketplaceTransaction $transaction, User $actor): MarketplaceTransaction
    {
        Gate::forUser($actor)->authorize('complete', $transaction);

        return $this->transition($transaction, $actor, MarketplaceTransactionStatus::Completed, [MarketplaceTransactionStatus::InProgress], ['completed_at' => now()], 'marketplace_transaction.completed');
    }

    public function cancel(MarketplaceTransaction $transaction, User $actor, ?string $reason = null): MarketplaceTransaction
    {
        Gate::forUser($actor)->authorize('cancel', $transaction);

        return $this->transition($transaction, $actor, MarketplaceTransactionStatus::Cancelled, [MarketplaceTransactionStatus::Pending, MarketplaceTransactionStatus::Paid, MarketplaceTransactionStatus::InProgress], [
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ], 'marketplace_transaction.cancelled');
    }

    public function refund(MarketplaceTransaction $transaction, User $actor, ?string $reason = null): MarketplaceTransaction
    {
        Gate::forUser($actor)->authorize('refund', $transaction);

        return $this->transition($transaction, $actor, MarketplaceTransactionStatus::Refunded, [MarketplaceTransactionStatus::Paid, MarketplaceTransactionStatus::InProgress, MarketplaceTransactionStatus::Cancelled], [
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ], 'marketplace_transaction.refunded');
    }

    /**
     * @param list<MarketplaceTransactionStatus> $allowedFrom
     * @param array<string, mixed> $updates
     */
    private function transition(MarketplaceTransaction $transaction, User $actor, MarketplaceTransactionStatus $to, array $allowedFrom, array $updates, string $event): MarketplaceTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $to, $allowedFrom, $updates, $event): MarketplaceTransaction {
            /** @var MarketplaceTransaction $transaction */
            $transaction = MarketplaceTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $from = $transaction->status;

            if (in_array($from, $allowedFrom, true) === false) {
                throw new DomainStateTransitionException("Invalid marketplace transaction transition from {$from->value} to {$to->value}.");
            }

            $transaction->forceFill(array_merge($updates, ['status' => $to]))->save();
            AuditLogger::record(event: $event, auditable: $transaction, before: ['status' => $from->value], after: ['status' => $to->value], actor: $actor);

            return $transaction->refresh();
        });
    }
}
