<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlanEntitlement>
 */
final class SubscriptionPlanEntitlementFactory extends Factory
{
    protected $model = SubscriptionPlanEntitlement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'quantity' => 10,
            'sort_order' => 0,
        ];
    }
}
