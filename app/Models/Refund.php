<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RefundStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $provider
 * @property string|null $provider_refund_id
 * @property string|null $reason
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $failed_at
 * @property array<string, mixed>|null $provider_metadata
 */
class Refund extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'evaluation_request_id',
        'actor_id',
        'amount_minor',
        'currency',
        'status',
        'provider',
        'provider_refund_id',
        'reason',
        'requested_at',
        'processed_at',
        'failed_at',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => RefundStatus::class,
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $refund): void {
            if ($refund->status !== RefundStatus::Pending) {
                throw new DomainStateTransitionException('Refunds must be created in the pending state.');
            }
        });

        static::updating(function (self $refund): void {
            $immutableFields = [
                'order_id',
                'payment_id',
                'evaluation_request_id',
                'actor_id',
                'amount_minor',
                'currency',
                'provider',
                'reason',
                'requested_at',
            ];

            if (array_intersect(array_keys($refund->getDirty()), $immutableFields) !== []) {
                throw new DomainStateTransitionException('Refund provenance and commercial terms are immutable after creation.');
            }
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Refund records cannot be deleted; they are part of the historical commerce record.');
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<EvaluationRequest, $this> */
    public function evaluationRequest(): BelongsTo
    {
        return $this->belongsTo(EvaluationRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
