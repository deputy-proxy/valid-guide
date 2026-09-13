<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionBillingStatus;
use App\Models\Subscription;
use App\Models\SubscriptionBillingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionBillingRecord>
 */
final class SubscriptionBillingRecordFactory extends Factory
{
    protected $model = SubscriptionBillingRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $periodStart = now();

        return [
            'subscription_id' => Subscription::factory(),
            'sequence' => 1,
            'status' => SubscriptionBillingStatus::Pending,
            'amount_minor' => 1900,
            'currency' => 'EUR',
            'period_start' => $periodStart,
            'period_end' => $periodStart->copy()->addMonth(),
        ];
    }
}
