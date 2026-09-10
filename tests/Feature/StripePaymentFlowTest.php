<?php

declare(strict_types=1);

use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\EvaluationRequest;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CreatorEvaluationRequestIntake;
use App\Services\DomainStateTransitionException;
use App\Services\StripePaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/** @return array{0: User, 1: Organization, 2: EvaluationRequest, 3: ServicePackage} */
function stripePaymentFixture(): array
{
    [$user, $organization, $product, $release, $package] = creatorWizardFixture();
    $intake = app(CreatorEvaluationRequestIntake::class);

    $request = $intake->start($user, $organization->id);
    $request = $intake->selectProduct($user, $request, $product);
    $request = $intake->selectRelease($user, $request, $release);
    $request = $intake->updateScope($user, $request, 'Evaluate the complete learning experience.');
    $request = $intake->saveMaterialDraft($user, $request, EvaluationMaterialType::Url, 'Course page', null, 'https://example.test/course');
    $request = $intake->confirmClaimsAndAudience($user, $request);
    $request = $intake->applyCommercialTerms($user, $request, $package->id, 'standard');
    $request = $intake->validateForPayment($user, $request);

    return [$user, $organization, $request->fresh(), $package];
}

function stripeWebhookPayload(string $eventId, string $type, int $paymentId, int $orderId, array $overrides = []): string
{
    $payload = [
        'id' => $eventId,
        'type' => $type,
        'data' => [
            'object' => array_merge([
                'id' => 'cs_test_'.$eventId,
                'metadata' => [
                    'payment_id' => (string) $paymentId,
                    'order_id' => (string) $orderId,
                ],
                'amount_total' => 25000,
                'currency' => 'eur',
                'payment_intent' => 'pi_test_'.$eventId,
            ], $overrides),
        ],
    ];

    return json_encode($payload, JSON_THROW_ON_ERROR);
}

function postSignedStripeWebhook(string $payload): TestResponse
{
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

    return test()->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ],
        $payload,
    );
}

it('creates a Stripe checkout with the frozen amount and currency', function () {
    [$user, , $request] = stripePaymentFixture();
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'payment_intent' => 'pi_test_123',
        ], 200),
    ]);
    config(['stripe.secret' => 'sk_test_secret']);

    $result = app(StripePaymentService::class)->createCheckout($request, $user);

    expect($result['url'])->toBe('https://checkout.stripe.com/c/pay/cs_test_123')
        ->and(Order::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['line_items[0][price_data][unit_amount]'] === '25000'
            && $request['line_items[0][price_data][currency]'] === 'eur'
            && $request['mode'] === 'payment';
    });
});

it('confirms payment only from a valid signed webhook and is idempotent', function () {
    [, , $request] = stripePaymentFixture();
    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Pending,
        'provider' => 'stripe',
    ]);
    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeWebhookPayload('evt_paid_1', 'checkout.session.completed', $payment->id, $order->id);

    postSignedStripeWebhook($payload)->assertOk();
    postSignedStripeWebhook($payload)->assertOk();

    $request->refresh();
    expect($request->status)->toBe(EvaluationRequestStatus::Paid)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(PaymentWebhookEvent::query()->where('provider_event_id', 'evt_paid_1')->count())->toBe(1);
});

it('rejects an invalid webhook signature without changing payment state', function () {
    [, , $request] = stripePaymentFixture();
    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Pending,
        'provider' => 'stripe',
    ]);
    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeWebhookPayload('evt_invalid_1', 'checkout.session.completed', $payment->id, $order->id);
    $response = $this->withHeaders(['Stripe-Signature' => 't='.time().',v1=invalid'])->postJson(
        '/webhooks/stripe',
        json_decode($payload, true, 512, JSON_THROW_ON_ERROR),
    );

    $response->assertStatus(400);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->refresh()->status)->toBe(EvaluationRequestStatus::AwaitingPayment)
        ->and(PaymentWebhookEvent::query()->count())->toBe(0);
});

it('fails a payment when Stripe reports an amount mismatch', function () {
    [, , $request] = stripePaymentFixture();
    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Pending,
        'provider' => 'stripe',
    ]);
    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeWebhookPayload('evt_mismatch_1', 'checkout.session.completed', $payment->id, $order->id, [
        'amount_total' => 9999,
    ]);

    postSignedStripeWebhook($payload)->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($request->refresh()->status)->toBe(EvaluationRequestStatus::AwaitingPayment);
});

it('does not create a second order for an already paid request', function () {
    [$user, , $request] = stripePaymentFixture();
    $order = Order::query()->create([
        'organization_id' => $request->organization_id,
        'evaluation_request_id' => $request->id,
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => OrderStatus::Paid,
        'provider' => 'stripe',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => 'stripe',
        'amount_minor' => 25000,
        'currency' => 'EUR',
        'status' => PaymentStatus::Paid,
    ]);

    expect(fn () => app(StripePaymentService::class)->createCheckout($request, $user))
        ->toThrow(DomainStateTransitionException::class);

    expect(Order::query()->where('evaluation_request_id', $request->id)->count())->toBe(1);
});
