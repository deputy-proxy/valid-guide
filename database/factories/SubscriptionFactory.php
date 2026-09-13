<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $plan = SubscriptionPlan::factory()->create();
        $organization = Organization::query()->first();

        if ($organization === null) {
            $organizationId = Organization::query()->insertGetId([
                'name' => fake()->company(),
                'slug' => fake()->unique()->slug(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $organizationId = $organization->getKey();
        }

        return [
            'organization_id' => $organizationId,
            'subscription_plan_id' => $plan->getKey(),
            'status' => SubscriptionStatus::Pending,
            'plan_code_snapshot' => $plan->code,
            'plan_name_snapshot' => $plan->name,
            'price_minor_snapshot' => $plan->price_minor,
            'currency_snapshot' => $plan->currency,
            'billing_interval_snapshot' => $plan->billing_interval,
            'billing_interval_count_snapshot' => $plan->billing_interval_count,
            'entitlements_snapshot' => [],
        ];
    }
}
