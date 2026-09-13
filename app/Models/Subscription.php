<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_plan_id
 * @property SubscriptionStatus $status
 * @property string|null $provider
 * @property string|null $provider_subscription_id
 * @property string $plan_code_snapshot
 * @property string $plan_name_snapshot
 * @property int $price_minor_snapshot
 * @property string $currency_snapshot
 * @property string $billing_interval_snapshot
 * @property int $billing_interval_count_snapshot
 * @property array<int, array<string, mixed>> $entitlements_snapshot
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $current_period_start
 * @property \Illuminate\Support\Carbon|null $current_period_end
 * @property bool $cancel_at_period_end
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon|null $recovered_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
#[Fillable([
    'organization_id',
    'subscription_plan_id',
    'status',
    'provider',
    'provider_subscription_id',
    'plan_code_snapshot',
    'plan_name_snapshot',
    'price_minor_snapshot',
    'currency_snapshot',
    'billing_interval_snapshot',
    'billing_interval_count_snapshot',
    'entitlements_snapshot',
    'started_at',
    'current_period_start',
    'current_period_end',
    'cancel_at_period_end',
    'cancelled_at',
    'cancellation_reason',
    'failed_at',
    'recovered_at',
    'refunded_at',
    'expires_at',
])]
final class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'price_minor_snapshot' => 'integer',
            'billing_interval_count_snapshot' => 'integer',
            'entitlements_snapshot' => 'array',
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'recovered_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $subscription): void {
            $immutable = [
                'organization_id',
                'subscription_plan_id',
                'provider',
                'provider_subscription_id',
                'plan_code_snapshot',
                'plan_name_snapshot',
                'price_minor_snapshot',
                'currency_snapshot',
                'billing_interval_snapshot',
                'billing_interval_count_snapshot',
                'entitlements_snapshot',
            ];

            if (array_intersect(array_keys($subscription->getDirty()), $immutable) !== []) {
                throw new DomainStateTransitionException('Subscription commercial terms are immutable.');
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /** @return HasMany<SubscriptionBillingRecord, $this> */
    public function billingRecords(): HasMany
    {
        return $this->hasMany(SubscriptionBillingRecord::class)->orderByDesc('sequence');
    }

    public function latestBillingRecord(): ?SubscriptionBillingRecord
    {
        return $this->billingRecords()->first();
    }
}
