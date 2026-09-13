<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionPlanStatus;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
final class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'status' => SubscriptionPlanStatus::Active,
            'price_minor' => 1900,
            'currency' => 'EUR',
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
        ];
    }
}
