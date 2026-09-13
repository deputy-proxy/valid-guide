<?php

declare(strict_types=1);

use App\Enums\SubscriptionBillingStatus;
use App\Enums\SubscriptionPlanStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanEntitlement;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\SubscriptionManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function subscriptionOrganization(string $name = 'Subscription Test'): array
{
    $organizationId = \Illuminate\Support\Facades\DB::table('organizations')->insertGetId([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $organization = Organization::query()->findOrFail($organizationId);
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => 'billing']);

    return [$organization, $user];
}

function subscriptionPlan(string $status = 'active'): SubscriptionPlan
{
    $plan = SubscriptionPlan::factory()->create([
        'status' => SubscriptionPlanStatus::from($status),
        'code' => 'plan-'.uniqid(),
        'price_minor' => 4900,
        'currency' => 'EUR',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);

    SubscriptionPlanEntitlement::query()->create([
        'subscription_plan_id' => $plan->id,
        'code' => 'evaluations',
        'name' => 'Evaluations',
        'description' => 'Monthly evaluation allowance.',
        'quantity' => 10,
        'sort_order' => 1,
    ]);

    return $plan->refresh();
}

it('creates a pending subscription with immutable commercial snapshots', function () {
    [$organization, $user] = subscriptionOrganization();
    $plan = subscriptionPlan();

    $subscription = app(SubscriptionManagement::class)->create($user, $organization, $plan);

    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->price_minor_snapshot)->toBe(4900)
        ->and($subscription->currency_snapshot)->toBe('EUR')
        ->and($subscription->plan_code_snapshot)->toBe($plan->code)
        ->and($subscription->entitlements_snapshot)->toHaveCount(1)
        ->and($subscription->latestBillingRecord()?->status)->toBe(SubscriptionBillingStatus::Pending)
        ->and(AuditLog::query()->where('event', 'subscription.created')->exists())->toBeTrue();
});

it('blocks creation for non-billing members and inactive plans', function () {
    [$organization, $billingUser] = subscriptionOrganization();
    $editor = User::factory()->create();
    $organization->users()->attach($editor, ['role' => 'editor']);
    $draft = subscriptionPlan('draft');
    $service = app(SubscriptionManagement::class);

    expect(fn () => $service->create($editor, $organization, $draft))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    expect(fn () => $service->create($billingUser, $organization, $draft))
        ->toThrow(DomainStateTransitionException::class, 'Only active subscription plans can be purchased.');
});

it('enforces organization isolation and allows billing users to see only their organization', function () {
    [$organization, $user] = subscriptionOrganization('First Organization');
    [$otherOrganization, $otherUser] = subscriptionOrganization('Second Organization');
    $plan = subscriptionPlan();

    $first = app(SubscriptionManagement::class)->create($user, $organization, $plan);
    $second = app(SubscriptionManagement::class)->create($otherUser, $otherOrganization, $plan);

    expect(Subscription::query()->where('organization_id', $organization->id)->pluck('id')->all())
        ->toBe([$first->id])
        ->and(Subscription::query()->where('organization_id', $otherOrganization->id)->pluck('id')->all())
        ->toBe([$second->id]);

    expect(fn () => app(SubscriptionManagement::class)->cancel($second, $user))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('supports activation, renewal, failed payment recovery and refund transitions', function () {
    [$organization, $user] = subscriptionOrganization();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $plan = subscriptionPlan();
    $service = app(SubscriptionManagement::class);
    $subscription = $service->create($user, $organization, $plan);

    $subscription = $service->activate($subscription, $admin, 'test', 'sub_123');
    $firstEnd = $subscription->current_period_end;

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->latestBillingRecord()?->status)->toBe(SubscriptionBillingStatus::Paid)
        ->and($firstEnd)->not->toBeNull();

    $subscription = $service->renew($subscription, $admin);
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_end?->greaterThan($firstEnd))->toBeTrue()
        ->and($subscription->billingRecords()->count())->toBe(2);

    $subscription = $service->failPayment($subscription, $admin, 'Card declined.');
    expect($subscription->status)->toBe(SubscriptionStatus::PaymentFailed)
        ->and($subscription->latestBillingRecord()?->status)->toBe(SubscriptionBillingStatus::Failed);

    $subscription = $service->recover($subscription, $admin);
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->latestBillingRecord()?->status)->toBe(SubscriptionBillingStatus::Paid);

    $subscription = $service->refund($subscription, $admin, 'Customer refund.');
    expect($subscription->status)->toBe(SubscriptionStatus::Refunded)
        ->and($subscription->latestBillingRecord()?->status)->toBe(SubscriptionBillingStatus::Refunded);
});

it('rejects invalid lifecycle transitions', function () {
    [$organization, $user] = subscriptionOrganization();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $subscription = app(SubscriptionManagement::class)->create($user, $organization, subscriptionPlan());

    expect(fn () => app(SubscriptionManagement::class)->renew($subscription, $admin))
        ->toThrow(DomainStateTransitionException::class);

    $subscription = app(SubscriptionManagement::class)->cancel($subscription, $user);

    expect(fn () => app(SubscriptionManagement::class)->activate($subscription, $admin))
        ->toThrow(DomainStateTransitionException::class);
});

it('keeps subscription commercial snapshots immutable after plan changes', function () {
    [$organization, $user] = subscriptionOrganization();
    $plan = subscriptionPlan();
    $subscription = app(SubscriptionManagement::class)->create($user, $organization, $plan);
    $originalPrice = $subscription->price_minor_snapshot;

    $plan->update(['price_minor' => 9900, 'name' => 'Changed Plan']);
    $subscription->refresh();

    expect($subscription->price_minor_snapshot)->toBe($originalPrice)
        ->and($subscription->plan_name_snapshot)->not->toBe('Changed Plan');

    expect(fn () => $subscription->update(['price_minor_snapshot' => 9900]))
        ->toThrow(DomainStateTransitionException::class, 'commercial terms are immutable');
});

it('can cancel and expire deterministically', function () {
    [$organization, $user] = subscriptionOrganization();
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $service = app(SubscriptionManagement::class);
    $subscription = $service->activate($service->create($user, $organization, subscriptionPlan()), $admin);

    $subscription = $service->expire($subscription, $admin);
    expect($subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->expires_at)->not->toBeNull();

    $subscription = $service->create($user, $organization, subscriptionPlan());
    $subscription = $service->cancel($subscription, $user, 'No longer needed.');
    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->cancellation_reason)->toBe('No longer needed.');
});
