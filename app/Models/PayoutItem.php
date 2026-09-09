<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = ['payout_id', 'auditor_compensation_id', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            throw new DomainStateTransitionException('Payout items are immutable.');
        });

        static::deleting(function (self $item): void {
            throw new DomainStateTransitionException('Payout items cannot be deleted.');
        });
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    /** @return BelongsTo<AuditorCompensation, $this> */
    public function compensation(): BelongsTo
    {
        return $this->belongsTo(AuditorCompensation::class, 'auditor_compensation_id');
    }
}
