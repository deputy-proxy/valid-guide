<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionBillingStatus;
use App\Services\DomainStateTransitionException;
use Database\Factories\SubscriptionBillingRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $subscription_id
 * @property int $sequence
 * @property SubscriptionBillingStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $provider
 * @property string|null $provider_payment_id
 * @property string|null $provider_reference
 * @property Carbon $period_start
 * @property Carbon $period_end
 */
#[Fillable([
    'subscription_id',
    'sequence',
    'status',
    'amount_minor',
    'currency',
    'provider',
    'provider_payment_id',
    'provider_reference',
    'period_start',
    'period_end',
    'due_at',
    'paid_at',
    'failed_at',
    'refunded_at',
    'cancelled_at',
    'failure_reason',
    'refund_reason',
])]
final class SubscriptionBillingRecord extends Model
{
    /** @use HasFactory<SubscriptionBillingRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionBillingStatus::class,
            'amount_minor' => 'integer',
            'sequence' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $record): void {
            $immutable = [
                'subscription_id',
                'sequence',
                'amount_minor',
                'currency',
                'period_start',
                'period_end',
            ];

            if (array_intersect(array_keys($record->getDirty()), $immutable) !== []) {
                throw new DomainStateTransitionException('Subscription billing terms are immutable.');
            }
        });
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
