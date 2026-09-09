<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditor_id', 'amount_minor', 'currency', 'status', 'paid_at', 'payment_reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $payout): void {
            if ($payout->getOriginal('paid_at') !== null) {
                throw new DomainStateTransitionException('Paid payouts are immutable.');
            }

            if (array_intersect(array_keys($payout->getDirty()), ['auditor_id', 'amount_minor', 'currency']) !== []) {
                throw new DomainStateTransitionException('Payout identity and amount are immutable.');
            }
        });

        static::deleting(function (self $payout): void {
            throw new DomainStateTransitionException('Payouts cannot be deleted.');
        });
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
