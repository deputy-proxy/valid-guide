<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketplaceServiceStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                      $auditor_profile_id
 * @property MarketplaceServiceStatus $status
 * @property list<string>|null        $expertise_areas
 * @property list<string>|null        $product_types
 * @property int                      $price_minor
 * @property string                   $currency
 */
class MarketplaceService extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_profile_id',
        'title',
        'slug',
        'description',
        'expertise_areas',
        'product_types',
        'price_minor',
        'currency',
        'status',
        'published_at',
        'paused_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarketplaceServiceStatus::class,
            'expertise_areas' => 'array',
            'product_types' => 'array',
            'price_minor' => 'integer',
            'published_at' => 'datetime',
            'paused_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $service): void {
            if ($service->getRawOriginal('status') !== MarketplaceServiceStatus::Draft->value) {
                $allowed = ['status', 'published_at', 'paused_at', 'archived_at', 'updated_at'];
                $changed = array_diff(array_keys($service->getDirty()), $allowed);

                if ($changed !== []) {
                    throw new DomainStateTransitionException('Published marketplace service details are immutable.');
                }
            }
        });

        static::deleting(function (self $service): void {
            if ($service->transactions()->exists()) {
                throw new DomainStateTransitionException('Marketplace services with transaction history cannot be deleted.');
            }

            if ($service->status !== MarketplaceServiceStatus::Draft) {
                throw new DomainStateTransitionException('Published marketplace services cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function auditorProfile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class);
    }

    /** @return HasMany<MarketplaceTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(MarketplaceTransaction::class);
    }
}
