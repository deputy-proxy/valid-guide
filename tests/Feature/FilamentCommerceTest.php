<?php

declare(strict_types=1);

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\EvaluationRequests\Pages\ListEvaluationRequests;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\CreatorRefundService;
use App\Services\DomainStateTransitionException;
use App\Services\ReportDelivery;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows only platform admins to access the commerce resource', function () {
    $admin = User::factory()->create(['platform_role' => 'admin']);
    $creator = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListEvaluationRequests::class)
        ->assertSuccessful();

    Livewire::actingAs($creator)
        ->test(ListEvaluationRequests::class)
        ->assertForbidden();
});

it('presents payment states without exposing provider metadata', function () {
    [, , $request] = stripePaymentFixture();
    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Failed,
        'provider' => 'stripe',
        'provider_reference' => 'cs_internal_reference',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_internal_reference',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Failed,
        'provider_metadata' => [
            'secret' => 'sk_live_never_display_this',
            'webhook_signature' => 'whsec_never_display_this',
        ],
    ]);

    $admin = User::factory()->create(['platform_role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(ListEvaluationRequests::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$request])
        ->assertSee('Failed')
        ->assertDontSee('sk_live_never_display_this')
        ->assertDontSee('whsec_never_display_this');
});

it('starts a payment retry through the existing Stripe payment service', function () {
    [, , $request] = stripePaymentFixture();
    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Failed,
        'provider' => 'stripe',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Failed,
    ]);

    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_retry_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_retry_123',
            'payment_intent' => 'pi_retry_123',
        ], 200),
    ]);
    config(['stripe.secret' => 'sk_test_secret']);

    $admin = User::factory()->create(['platform_role' => 'admin']);
    $component = Livewire::actingAs($admin)->test(ListEvaluationRequests::class);

    $component->callAction(TestAction::make('retryPayment')->table($request));

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(2);
    Http::assertSentCount(1);
});

it('refunds an eligible request through the Filament action', function () {
    [$user, $request, $order, $payment] = creatorRefundFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    Http::fake([
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_filament_123',
            'status' => 'succeeded',
        ], 200),
    ]);
    config(['stripe.secret' => 'sk_test_secret']);

    Livewire::actingAs($admin)
        ->test(ListEvaluationRequests::class)
        ->callAction(TestAction::make('refund')->table($request))
        ->assertNotified('Refund completed');

    expect($request->fresh()->status)->toBe(EvaluationRequestStatus::Refunded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(Refund::query()->count())->toBe(1)
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('does not offer or process a refund after report delivery', function () {
    [$request, $report] = reportDeliveryFixture();
    $admin = User::factory()->create(['platform_role' => 'admin']);

    $organization = $request->organization()->firstOrFail();
    $owner = $organization->users()->wherePivot('role', 'owner')->firstOrFail();

    $request->getConnection()->table('evaluation_requests')->where('id', $request->id)->update([
        'status' => EvaluationRequestStatus::Paid->value,
        'quoted_amount_minor' => 50000,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);
    $request->refresh();

    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 50000,
        'currency' => 'EUR',
        'status' => OrderStatus::Paid,
        'provider' => 'stripe',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_delivered_filament',
        'amount_minor' => 50000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    app(ReportDelivery::class)->deliver($report, $admin);

    expect(fn () => app(CreatorRefundService::class)->refund($request, $owner))
        ->toThrow(DomainStateTransitionException::class);

    Livewire::actingAs($admin)
        ->test(ListEvaluationRequests::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$request])
        ->assertTableActionHidden('refund', $request);

    expect(Refund::query()->count())->toBe(0);
});
