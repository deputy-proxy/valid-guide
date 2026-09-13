<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketplaceTransactionStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                          $marketplace_service_id
 * @property int                          $auditor_profile_id
 * @property int                          $buyer_id
 * @property int|null                     $organization_id
 * @property MarketplaceTransactionStatus $status
 * @property int                          $amount_minor
 * @property string                       $currency
 */
class MarketplaceTransaction extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'marketplace_service_id',
        'auditor_profile_id',
        'buyer_id',
        'organization_id',
        'amount_minor',
        'currency',
        'status',
        'provider',
        'provider_payment_id',
        'cancellation_reason',
        'refund_reason',
        'paid_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarketplaceTransactionStatus::class,
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $transaction): void {
            if ($transaction->getRawOriginal('status') === MarketplaceTransactionStatus::Completed->value) {
                $allowed = ['updated_at'];
                $changed = array_diff(array_keys($transaction->getDirty()), $allowed);

                if ($changed !== []) {
                    throw new DomainStateTransitionException('Completed marketplace transactions are immutable.');
                }
            }
        });
    }

    /** @return BelongsTo<MarketplaceService, $this> */
    public function marketplaceService(): BelongsTo
    {
        return $this->belongsTo(MarketplaceService::class);
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function auditorProfile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
