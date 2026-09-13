<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionPlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property SubscriptionPlanStatus $status
 * @property int $price_minor
 * @property string $currency
 * @property string $billing_interval
 * @property int $billing_interval_count
 */
#[Fillable([
    'code',
    'name',
    'description',
    'status',
    'price_minor',
    'currency',
    'billing_interval',
    'billing_interval_count',
])]
final class SubscriptionPlan extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionPlanStatus::class,
            'price_minor' => 'integer',
            'billing_interval_count' => 'integer',
        ];
    }

    /** @return HasMany<SubscriptionPlanEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(SubscriptionPlanEntitlement::class)->orderBy('sort_order')->orderBy('id');
    }
}
