<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $paid_at
 */
class Payout extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = ['auditor_id', 'amount_minor', 'currency', 'status', 'paid_at', 'payment_reference', 'notes'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payout): void {
            if ($payout->status !== null && $payout->status !== 'pending') {
                throw new DomainStateTransitionException('Payouts must be created as pending and completed through PayoutService.');
            }
            if ($payout->paid_at !== null || $payout->payment_reference !== null) {
                throw new DomainStateTransitionException('Payout payment details can only be recorded through PayoutService.');
            }
        });
        static::updating(function (self $payout): void {
            if ($payout->getOriginal('paid_at') !== null) {
                throw new DomainStateTransitionException('Paid payouts are immutable.');
            }
            if (array_intersect(array_keys($payout->getDirty()), ['auditor_id', 'amount_minor', 'currency']) !== []) {
                throw new DomainStateTransitionException('Payout identity and amount are immutable.');
            }
            if ($payout->isDirty('status') || $payout->isDirty('paid_at') || $payout->isDirty('payment_reference')) {
                throw new DomainStateTransitionException('Payout payment state can only be changed through PayoutService.');
            }
        });
        static::deleting(function (self $payout): void {
            throw new DomainStateTransitionException('Payouts cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /** @return HasMany<PayoutItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
