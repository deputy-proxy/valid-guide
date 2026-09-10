<?php

declare(strict_types=1);

use App\Auth\Access\AuthorizationException;
use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\EvaluationRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\CreatorRefundService;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: EvaluationRequest, 2: Order, 3: Payment} */
function creatorRefundFixture(): array
{
    [$user, , $request] = stripePaymentFixture();

    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => $request->quoted_amount_minor,
        'currency' => strtoupper((string) $request->currency),
        'status' => OrderStatus::Paid,
        'provider' => 'stripe',
        'provider_reference' => 'cs_refund_'.$request->id,
    ]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_refund_'.$request->id,
        'amount_minor' => $request->quoted_amount_minor,
        'currency' => strtoupper((string) $request->currency),
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    return [$user, $request->fresh(), $order, $payment];
}

test('a paid creator request can receive a full refund before report delivery', function () {
    [$user, $request, $order, $payment] = creatorRefundFixture();
    $paymentIntent = $payment->provider_payment_id;
    $amount = (string) $payment->amount_minor;

    Http::fake([
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_test_123',
            'status' => 'succeeded',
        ], 200),
    ]);
    config(['stripe.secret' => 'sk_test_secret']);

    $refund = app(CreatorRefundService::class)->refund($request, $user, 'No longer needed');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->amount_minor)->toBe($payment->amount_minor)
        ->and($refund->currency)->toBe('EUR')
        ->and($refund->provider_refund_id)->toBe('re_test_123')
        ->and($refund->reason)->toBe('No longer needed')
        ->and($refund->actor_id)->toBe($user->id)
        ->and($refund->requested_at)->not->toBeNull()
        ->and($refund->processed_at)->not->toBeNull()
        ->and($request->fresh()->status)->toBe(EvaluationRequestStatus::Refunded)
        ->and($request->fresh()->refunded_at)->not->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);

    Http::assertSent(function (Request $request) use ($paymentIntent, $amount): bool {
        $idempotencyKey = $request->header('Idempotency-Key')[0] ?? null;

        return $request->url() === 'https://api.stripe.com/v1/refunds'
            && $request['payment_intent'] === $paymentIntent
            && $request['amount'] === $amount
            && is_string($idempotencyKey)
            && str_starts_with($idempotencyKey, 'refund_');
    });
});

test('a delivered report permanently closes the creator refund boundary', function () {
    [$request, $report] = reportDeliveryFixture();
    $user = $request->organization()->firstOrFail()->users()->wherePivot('role', 'owner')->firstOrFail();

    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => $request->quoted_amount_minor,
        'currency' => strtoupper((string) $request->currency),
        'status' => OrderStatus::Paid,
        'provider' => 'stripe',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_delivered_'.$request->id,
        'amount_minor' => $request->quoted_amount_minor,
        'currency' => strtoupper((string) $request->currency),
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $admin = User::factory()->create(['platform_role' => 'admin']);
    app(\App\Services\ReportDelivery::class)->deliver($report, $admin);

    expect(fn () => app(CreatorRefundService::class)->refund($request, $user))
        ->toThrow(DomainStateTransitionException::class);

    expect(Refund::query()->count())->toBe(0)
        ->and($request->fresh()->status)->toBe(EvaluationRequestStatus::Paid);
});

test('a Stripe failure leaves the payment paid and the refund retryable', function () {
    [$user, $request, $order, $payment] = creatorRefundFixture();

    Http::fakeSequence()
        ->pushStatus(500)
        ->push([
            'id' => 're_retry_123',
            'status' => 'succeeded',
        ], 200);
    config(['stripe.secret' => 'sk_test_secret']);

    expect(fn () => app(CreatorRefundService::class)->refund($request, $user))
        ->toThrow(RequestException::class);

    $failedRefund = Refund::query()->firstOrFail();
    expect($failedRefund->status)->toBe(RefundStatus::Failed)
        ->and($failedRefund->failed_at)->not->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($request->fresh()->status)->toBe(EvaluationRequestStatus::Paid);

    $refund = app(CreatorRefundService::class)->refund($request, $user);

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBe('re_retry_123')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($request->fresh()->status)->toBe(EvaluationRequestStatus::Refunded);
});

test('repeated refund requests do not create a second provider refund', function () {
    [$user, $request] = creatorRefundFixture();

    Http::fake([
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_duplicate_123',
            'status' => 'succeeded',
        ], 200),
    ]);
    config(['stripe.secret' => 'sk_test_secret']);

    $first = app(CreatorRefundService::class)->refund($request, $user);
    $second = app(CreatorRefundService::class)->refund($request->fresh(), $user);

    expect($first->id)->toBe($second->id)
        ->and(Refund::query()->count())->toBe(1);

    Http::assertSentCount(1);
});

test('billing-only members cannot request a creator refund', function () {
    [$user, $request] = creatorRefundFixture();
    $billingUser = User::factory()->create();
    $request->organization()->firstOrFail()->users()->attach($billingUser, ['role' => 'billing']);

    expect(fn () => app(CreatorRefundService::class)->refund($request, $billingUser))
        ->toThrow(AuthorizationException::class);

    expect(Refund::query()->count())->toBe(0);
});

test('refund records cannot be duplicated for one payment', function () {
    [$user, $request, $order, $payment] = creatorRefundFixture();

    $refund = Refund::query()->create([
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'evaluation_request_id' => $request->id,
        'actor_id' => $user->id,
        'amount_minor' => $payment->amount_minor,
        'currency' => $payment->currency,
        'status' => RefundStatus::Pending,
        'provider' => 'stripe',
        'requested_at' => now(),
    ]);

    expect(fn () => Refund::query()->create([
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'evaluation_request_id' => $request->id,
        'actor_id' => $user->id,
        'amount_minor' => $payment->amount_minor,
        'currency' => $payment->currency,
        'status' => RefundStatus::Pending,
        'provider' => 'stripe',
        'requested_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect($refund->exists)->toBeTrue();
});

test('refund provenance and commercial terms are immutable', function () {
    [$user, $request, $order, $payment] = creatorRefundFixture();

    $refund = Refund::query()->create([
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'evaluation_request_id' => $request->id,
        'actor_id' => $user->id,
        'amount_minor' => $payment->amount_minor,
        'currency' => $payment->currency,
        'status' => RefundStatus::Pending,
        'provider' => 'stripe',
        'requested_at' => now(),
    ]);

    $refund->amount_minor = 1;

    expect(fn () => $refund->save())->toThrow(DomainStateTransitionException::class);
});
