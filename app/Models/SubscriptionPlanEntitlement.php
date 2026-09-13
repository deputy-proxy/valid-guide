<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $subscription_plan_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $quantity
 * @property int $sort_order
 */
#[Fillable([
    'subscription_plan_id',
    'code',
    'name',
    'description',
    'quantity',
    'sort_order',
])]
final class SubscriptionPlanEntitlement extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPlanEntitlementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
