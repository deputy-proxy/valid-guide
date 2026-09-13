<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Enums\SubscriptionBillingStatus;
use App\Enums\SubscriptionPlanStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionBillingRecord;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SubscriptionManagement
{
    public function create(User $actor, Organization $organization, SubscriptionPlan $plan): Subscription
    {
        Gate::forUser($actor)->authorize('create', [Subscription::class, $organization]);

        return DB::transaction(function () use ($actor, $organization, $plan): Subscription {
            $this->assertBillingMembership($actor, $organization);
            $plan = SubscriptionPlan::query()->with('entitlements')->lockForUpdate()->whereKey($plan->getKey())->firstOrFail();

            if ($plan->status !== SubscriptionPlanStatus::Active) {
                throw new DomainStateTransitionException('Only active subscription plans can be purchased.');
            }

            $existing = Subscription::query()
                ->where('organization_id', $organization->getKey())
                ->whereIn('status', [
                    SubscriptionStatus::Pending,
                    SubscriptionStatus::Active,
                    SubscriptionStatus::PaymentFailed,
                ])
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new DomainStateTransitionException('The organization already has an open subscription.');
            }

            $subscription = Subscription::query()->create([
                'organization_id' => $organization->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Pending,
                'plan_code_snapshot' => $plan->code,
                'plan_name_snapshot' => $plan->name,
                'price_minor_snapshot' => $plan->price_minor,
                'currency_snapshot' => strtoupper($plan->currency),
                'billing_interval_snapshot' => $plan->billing_interval,
                'billing_interval_count_snapshot' => $plan->billing_interval_count,
                'entitlements_snapshot' => $plan->entitlements->map(static fn ($entitlement): array => [
                    'code' => $entitlement->code,
                    'name' => $entitlement->name,
                    'description' => $entitlement->description,
                    'quantity' => $entitlement->quantity,
                ])->values()->all(),
            ]);

            $this->createBillingRecord($subscription, 1, SubscriptionBillingStatus::Pending, null);
            AuditLogger::record(
                event: 'subscription.created',
                auditable: $subscription,
                after: [
                    'status' => SubscriptionStatus::Pending->value,
                    'plan_code' => $subscription->plan_code_snapshot,
                    'price_minor' => $subscription->price_minor_snapshot,
                    'currency' => $subscription->currency_snapshot,
                ],
                actor: $actor,
            );

            return $subscription->refresh();
        });
    }

    public function activate(Subscription $subscription, User $actor, ?string $provider = null, ?string $providerSubscriptionId = null): Subscription
    {
        Gate::forUser($actor)->authorize('activate', $subscription);

        return DB::transaction(function () use ($subscription, $actor, $provider, $providerSubscriptionId): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::Pending], SubscriptionStatus::Active);

            $now = \Illuminate\Support\Carbon::now();
            $end = $this->periodEnd($now, $subscription);
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'provider' => $provider,
                'provider_subscription_id' => $providerSubscriptionId,
                'started_at' => $now,
                'current_period_start' => $now,
                'current_period_end' => $end,
                'failed_at' => null,
            ])->save();

            $billing = $subscription->billingRecords()->lockForUpdate()->latest('sequence')->firstOrFail();
            $billing->forceFill([
                'status' => SubscriptionBillingStatus::Paid,
                'provider' => $provider,
                'provider_payment_id' => $providerSubscriptionId,
                'paid_at' => $now,
            ])->save();

            AuditLogger::record(event: 'subscription.activated', auditable: $subscription, before: ['status' => SubscriptionStatus::Pending->value], after: ['status' => SubscriptionStatus::Active->value], actor: $actor);

            return $subscription->refresh();
        });
    }

    public function renew(Subscription $subscription, User $actor): Subscription
    {
        Gate::forUser($actor)->authorize('renew', $subscription);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::Active], SubscriptionStatus::Active);

            $previousEnd = $subscription->current_period_end ?? \Illuminate\Support\Carbon::now();
            $start = $previousEnd->isFuture() ? $previousEnd : \Illuminate\Support\Carbon::now();
            $end = $this->periodEnd($start, $subscription);
            $sequence = ((int) $subscription->billingRecords()->lockForUpdate()->max('sequence')) + 1;

            $subscription->forceFill([
                'current_period_start' => $start,
                'current_period_end' => $end,
            ])->save();

            $this->createBillingRecord($subscription, $sequence, SubscriptionBillingStatus::Paid, $start);
            AuditLogger::record(event: 'subscription.renewed', auditable: $subscription, after: ['status' => SubscriptionStatus::Active->value, 'period_end' => $end->toIso8601String(), 'billing_sequence' => $sequence], actor: $actor);

            return $subscription->refresh();
        });
    }

    public function failPayment(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        Gate::forUser($actor)->authorize('failPayment', $subscription);

        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::Pending, SubscriptionStatus::Active], SubscriptionStatus::PaymentFailed);

            $billing = $subscription->billingRecords()->lockForUpdate()->latest('sequence')->firstOrFail();
            $billing->forceFill([
                'status' => SubscriptionBillingStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $reason,
            ])->save();

            $from = $subscription->status;
            $subscription->forceFill([
                'status' => SubscriptionStatus::PaymentFailed,
                'failed_at' => now(),
                'cancel_at_period_end' => false,
            ])->save();
            AuditLogger::record(event: 'subscription.payment_failed', auditable: $subscription, before: ['status' => $from->value], after: ['status' => SubscriptionStatus::PaymentFailed->value, 'reason' => $reason], actor: $actor);

            return $subscription->refresh();
        });
    }

    public function recover(Subscription $subscription, User $actor): Subscription
    {
        Gate::forUser($actor)->authorize('recover', $subscription);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::PaymentFailed], SubscriptionStatus::Active);

            $now = \Illuminate\Support\Carbon::now();
            $start = $subscription->current_period_end?->isFuture() ? $subscription->current_period_end : $now;
            $end = $this->periodEnd($start, $subscription);
            $sequence = ((int) $subscription->billingRecords()->lockForUpdate()->max('sequence')) + 1;

            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'recovered_at' => $now,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'failed_at' => null,
            ])->save();

            $this->createBillingRecord($subscription, $sequence, SubscriptionBillingStatus::Paid, $start);
            AuditLogger::record(event: 'subscription.recovered', auditable: $subscription, before: ['status' => SubscriptionStatus::PaymentFailed->value], after: ['status' => SubscriptionStatus::Active->value], actor: $actor);

            return $subscription->refresh();
        });
    }

    public function refund(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        Gate::forUser($actor)->authorize('refund', $subscription);

        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], SubscriptionStatus::Refunded);

            $billing = $subscription->billingRecords()->lockForUpdate()->latest('sequence')->firstOrFail();
            $billing->forceFill([
                'status' => SubscriptionBillingStatus::Refunded,
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ])->save();

            $from = $subscription->status;
            $subscription->forceFill([
                'status' => SubscriptionStatus::Refunded,
                'refunded_at' => now(),
            ])->save();
            AuditLogger::record(event: 'subscription.refunded', auditable: $subscription, before: ['status' => $from->value], after: ['status' => SubscriptionStatus::Refunded->value, 'reason' => $reason], actor: $actor);

            return $subscription->refresh();
        });
    }

    public function cancel(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        Gate::forUser($actor)->authorize('cancel', $subscription);

        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], SubscriptionStatus::Cancelled);

            $from = $subscription->status;
            $subscription->forceFill([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancel_at_period_end' => false,
            ])->save();

            $billing = $subscription->billingRecords()->lockForUpdate()->latest('sequence')->first();
            if ($billing !== null && $billing->status === SubscriptionBillingStatus::Pending) {
                $billing->forceFill([
                    'status' => SubscriptionBillingStatus::Cancelled,
                    'cancelled_at' => now(),
                ])->save();
            }

            AuditLogger::record(event: 'subscription.cancelled', auditable: $subscription, before: ['status' => $from->value], after: ['status' => SubscriptionStatus::Cancelled->value, 'reason' => $reason], actor: $actor);

            return $subscription->refresh();
        });
    }

    public function expire(Subscription $subscription, User $actor): Subscription
    {
        Gate::forUser($actor)->authorize('expire', $subscription);

        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $subscription = $this->locked($subscription);
            $this->assertTransition($subscription, [SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], SubscriptionStatus::Expired);

            $from = $subscription->status;
            $expiresAt = $subscription->current_period_end ?? \Illuminate\Support\Carbon::now();
            $subscription->forceFill([
                'status' => SubscriptionStatus::Expired,
                'expires_at' => $expiresAt,
            ])->save();

            AuditLogger::record(event: 'subscription.expired', auditable: $subscription, before: ['status' => $from->value], after: ['status' => SubscriptionStatus::Expired->value], actor: $actor);

            return $subscription->refresh();
        });
    }

    private function locked(Subscription $subscription): Subscription
    {
        return Subscription::query()->lockForUpdate()->whereKey($subscription->getKey())->firstOrFail();
    }

    /** @param list<SubscriptionStatus> $from */
    private function assertTransition(Subscription $subscription, array $from, SubscriptionStatus $to): void
    {
        if (in_array($subscription->status, $from, true) === false) {
            throw new DomainStateTransitionException("Invalid subscription transition from {$subscription->status->value} to {$to->value}.");
        }
    }

    private function assertBillingMembership(User $actor, Organization $organization): void
    {
        if ($actor->isPlatformAdmin()) {
            return;
        }

        $allowed = [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
            OrganizationRole::Billing->value,
        ];

        if ($organization->users()->whereKey($actor->getKey())->wherePivotIn('role', $allowed)->exists() === false) {
            throw new DomainStateTransitionException('The user is not authorized to manage billing for this organization.');
        }
    }

    private function periodEnd(CarbonInterface $start, Subscription $subscription): CarbonInterface
    {
        return match ($subscription->billing_interval_snapshot) {
            'day' => $start->copy()->addDays($subscription->billing_interval_count_snapshot),
            'week' => $start->copy()->addWeeks($subscription->billing_interval_count_snapshot),
            'year' => $start->copy()->addYears($subscription->billing_interval_count_snapshot),
            default => $start->copy()->addMonths($subscription->billing_interval_count_snapshot),
        };
    }

    private function createBillingRecord(Subscription $subscription, int $sequence, SubscriptionBillingStatus $status, ?\Illuminate\Support\Carbon $paidAt): SubscriptionBillingRecord
    {
        $periodStart = $subscription->current_period_start ?? \Illuminate\Support\Carbon::now();
        $periodEnd = $subscription->current_period_end ?? $this->periodEnd($periodStart, $subscription);

        return $subscription->billingRecords()->create([
            'sequence' => $sequence,
            'status' => $status,
            'amount_minor' => $subscription->price_minor_snapshot,
            'currency' => $subscription->currency_snapshot,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'paid_at' => $paidAt,
        ]);
    }
}
