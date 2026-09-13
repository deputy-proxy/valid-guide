<?php

declare(strict_types=1);

use App\Enums\NotificationEventType;
use App\Enums\OrganizationRole;
use App\Enums\PlatformRole;
use App\Enums\ValidationStatus;
use App\Enums\ValidationTrustMonitorCadence;
use App\Models\PublicDirectoryEntry;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanEntitlement;
use App\Models\User;
use App\Services\PublicVerificationPublication;
use App\Services\SubscriptionManagement;
use App\Services\ValidationStateTransition;
use App\Services\ValidationTrustMonitoring;
use Illuminate\Notifications\DatabaseNotification;

it('exercises the phase 5 public trust lifecycle across discovery, verification, monitoring and billing', function (): void {
    $validation = publicVerificationFixture();
    app(PublicVerificationPublication::class)->publish($validation);

    $entry = PublicDirectoryEntry::query()
        ->where('verification_identifier', $validation->verification_identifier)
        ->firstOrFail();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('public.directory'), false)
        ->assertSee(route('public.verify'), false);

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertSee($validation->verification_identifier);

    $this->get(route('public.products.show', ['slug' => $entry->slug]))
        ->assertOk()
        ->assertSee($validation->verification_identifier)
        ->assertSee(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]), false);

    $this->get(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]))
        ->assertOk()
        ->assertSee('Test Product 1.0')
        ->assertSee('Active');

    $organization = $validation->productRelease->product->organization;
    $monitorOwner = User::factory()->create();
    $organization->users()->attach($monitorOwner, ['role' => OrganizationRole::Editor->value]);

    $plan = SubscriptionPlan::factory()->create([
        'code' => 'phase-5-integration-plan',
        'name' => 'Phase 5 Integration',
    ]);
    SubscriptionPlanEntitlement::query()->create([
        'subscription_plan_id' => $plan->getKey(),
        'code' => 'validation-monitoring',
        'name' => 'Validation monitoring',
        'description' => 'Monitoring entitlement for the integration test.',
        'quantity' => 1,
        'sort_order' => 1,
    ]);

    $billingUser = User::factory()->create();
    $organization->users()->attach($billingUser, ['role' => OrganizationRole::Billing->value]);

    $subscription = app(SubscriptionManagement::class)->create($billingUser, $organization, $plan);

    expect($validation->refresh()->status)->toBe(ValidationStatus::Active)
        ->and($entry->refresh()->directory_visible)->toBeTrue()
        ->and(DatabaseNotification::query()
            ->where('notifiable_id', $billingUser->getKey())
            ->where('data->event_type', NotificationEventType::SubscriptionCreated->value)
            ->exists())->toBeTrue();

    $monitoring = app(ValidationTrustMonitoring::class);
    $monitor = $monitoring->configure($validation, $monitorOwner, ValidationTrustMonitorCadence::Hourly);
    $monitoring->runOne($monitor, now());

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
    app(ValidationStateTransition::class)->transition(
        $validation,
        ValidationStatus::Suspended,
        $admin,
        'Phase 5 integration suspension.',
    );

    $monitor->refresh()->forceFill(['next_check_at' => now()->subMinute()])->save();
    $monitoring->runOne($monitor, now());

    expect($subscription->refresh()->status->value)->toBe('pending')
        ->and($validation->refresh()->status)->toBe(ValidationStatus::Suspended)
        ->and($entry->refresh()->validation_status)->toBe(ValidationStatus::Suspended)
        ->and(DatabaseNotification::query()
            ->where('notifiable_id', $monitorOwner->getKey())
            ->where('data->event_type', NotificationEventType::TrustStateChanged->value)
            ->exists())->toBeTrue();

    $this->get(route('public.directory'))
        ->assertOk()
        ->assertDontSee($validation->verification_identifier);

    $this->get(route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]))
        ->assertOk()
        ->assertSee('Suspended')
        ->assertSee('Phase 5 integration suspension.');
});
