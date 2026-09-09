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
 * @property int $auditor_assignment_id
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $status
 * @property string|null $status_reason
 * @property CarbonImmutable|null $payable_at
 * @property CarbonImmutable|null $forfeited_at
 * @property CarbonImmutable|null $paid_at
 */
class AuditorCompensation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_assignment_id', 'amount_minor', 'currency', 'status', 'payable_at',
        'forfeited_at', 'paid_at', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payable_at' => 'datetime',
            'forfeited_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $compensation): void {
            if ($compensation->status !== null && $compensation->status !== 'pending') {
                throw new DomainStateTransitionException('Auditor compensation must be created as pending and finalized through AuditorCompensationService.');
            }
        });

        static::updating(function (self $compensation): void {
            if ($compensation->getOriginal('paid_at') !== null) {
                throw new DomainStateTransitionException('Paid auditor compensation is immutable.');
            }

            if (array_intersect(array_keys($compensation->getDirty()), ['auditor_assignment_id', 'amount_minor', 'currency']) !== []) {
                throw new DomainStateTransitionException('Auditor compensation identity and amount are immutable.');
            }
        });

        static::deleting(function (self $compensation): void {
            throw new DomainStateTransitionException('Auditor compensation cannot be deleted.');
        });
    }

    /** @return BelongsTo<AuditorAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AuditorAssignment::class, 'auditor_assignment_id');
    }

    /** @return HasMany<PayoutItem, $this> */
    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'auditor_compensation_id');
    }
}
