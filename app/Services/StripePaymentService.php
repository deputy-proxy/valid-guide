<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\EvaluationRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripePaymentService
{
    /** @return array{url: string, session_id: string, order_id: int, payment_id: int} */
    public function createCheckout(EvaluationRequest $request, User $actor): array
    {
        if ($request->status !== EvaluationRequestStatus::AwaitingPayment) {
            throw new DomainStateTransitionException('The evaluation request must be awaiting payment before a Stripe checkout can be created.');
        }

        if ($request->quoted_amount_minor === null || $request->currency === null || $request->service_package_name_snapshot === null) {
            throw new DomainStateTransitionException('Frozen commercial terms are required before creating a Stripe checkout.');
        }

        if ($request->organization_id === null) {
            throw new DomainStateTransitionException('The evaluation request must belong to an organization.');
        }

        $existingOrder = Order::query()
            ->where('evaluation_request_id', $request->getKey())
            ->lockForUpdate()
            ->first();

        if ($existingOrder?->status === OrderStatus::Paid) {
            throw new DomainStateTransitionException('This evaluation request has already been paid.');
        }

        $order = $existingOrder ?? Order::query()->create([
            'organization_id' => $request->organization_id,
            'evaluation_request_id' => $request->getKey(),
            'amount_minor' => $request->quoted_amount_minor,
            'currency' => strtoupper($request->currency),
            'status' => OrderStatus::Pending,
            'provider' => 'stripe',
        ]);

        $this->assertOrderMatchesRequest($order, $request);

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'provider' => 'stripe',
            'amount_minor' => $request->quoted_amount_minor,
            'currency' => strtoupper($request->currency),
            'status' => PaymentStatus::Pending,
        ]);

        $session = $this->requestCheckoutSession($request, $order, $payment);

        $payment->forceFill([
            'provider_payment_id' => $session['payment_intent'] ?? null,
            'provider_metadata' => [
                'checkout_session_id' => $session['id'],
            ],
        ])->save();

        $order->forceFill(['provider_reference' => $session['id']])->save();

        return [
            'url' => $session['url'],
            'session_id' => $session['id'],
            'order_id' => (int) $order->getKey(),
            'payment_id' => (int) $payment->getKey(),
        ];
    }

    /** @return array{id: string, url: string, payment_intent?: string|null} */
    private function requestCheckoutSession(EvaluationRequest $request, Order $order, Payment $payment): array
    {
        $secret = config('stripe.secret');
        if (is_string($secret) === false || $secret === '') {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $successUrl = $this->resolveRedirectUrl((string) config('stripe.success_url'), $request);
        $cancelUrl = $this->resolveRedirectUrl((string) config('stripe.cancel_url'), $request);

        $response = Http::withToken($secret)
            ->asForm()
            ->acceptJson()
            ->post(rtrim((string) config('stripe.api_url'), '/').'/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $request->getKey(),
                'metadata[order_id]' => (string) $order->getKey(),
                'metadata[payment_id]' => (string) $payment->getKey(),
                'metadata[evaluation_request_id]' => (string) $request->getKey(),
                'line_items[0][quantity]' => '1',
                'line_items[0][price_data][currency]' => strtolower((string) $request->currency),
                'line_items[0][price_data][unit_amount]' => (string) $request->quoted_amount_minor,
                'line_items[0][price_data][product_data][name]' => (string) $request->service_package_name_snapshot,
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $data = $response->json();
        if (is_array($data) === false
            || isset($data['id'], $data['url']) === false
            || is_string($data['id']) === false
            || is_string($data['url']) === false) {
            throw new RuntimeException('Stripe returned an invalid checkout session.');
        }

        return [
            'id' => $data['id'],
            'url' => $data['url'],
            'payment_intent' => isset($data['payment_intent']) && is_string($data['payment_intent']) ? $data['payment_intent'] : null,
        ];
    }

    private function resolveRedirectUrl(string $configuredUrl, EvaluationRequest $request): string
    {
        $url = str_starts_with($configuredUrl, 'http')
            ? $configuredUrl
            : url($configuredUrl);

        return $url.'?evaluation_request='.$request->getKey();
    }

    private function assertOrderMatchesRequest(Order $order, EvaluationRequest $request): void
    {
        if ($order->amount_minor !== $request->quoted_amount_minor
            || strtoupper($order->currency) !== strtoupper((string) $request->currency)
            || $order->provider !== 'stripe') {
            throw new DomainStateTransitionException('The internal order does not match the frozen commercial terms.');
        }
    }

    /** @param array<string, mixed> $event */
    public function confirmCheckoutPayment(array $event): void
    {
        $session = $event['data']['object'] ?? null;
        if (is_array($session) === false) {
            return;
        }

        $metadata = $session['metadata'] ?? null;
        if (is_array($metadata) === false || isset($metadata['payment_id'], $metadata['order_id']) === false) {
            return;
        }

        $paymentId = filter_var($metadata['payment_id'], FILTER_VALIDATE_INT);
        $orderId = filter_var($metadata['order_id'], FILTER_VALIDATE_INT);
        if (is_int($paymentId) === false || is_int($orderId) === false) {
            return;
        }

        DB::transaction(function () use ($paymentId, $orderId, $event, $session): void {
            $payment = Payment::query()->with('order')->lockForUpdate()->find($paymentId);
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($payment === null || $order === null || $payment->order_id !== $order->getKey()) {
                return;
            }

            if ($payment->status === PaymentStatus::Paid && $order->status === OrderStatus::Paid) {
                return;
            }

            $amount = isset($session['amount_total']) && is_int($session['amount_total']) ? $session['amount_total'] : null;
            $currency = isset($session['currency']) && is_string($session['currency']) ? strtoupper($session['currency']) : null;
            if ($amount !== $order->amount_minor || $currency !== strtoupper($order->currency)) {
                $payment->forceFill(['status' => PaymentStatus::Failed])->save();
                return;
            }

            $paidAt = now();
            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => $paidAt,
                'provider_payment_id' => isset($session['payment_intent']) && is_string($session['payment_intent']) ? $session['payment_intent'] : $payment->provider_payment_id,
                'provider_metadata' => $event,
            ])->save();

            $order->forceFill(['status' => OrderStatus::Paid])->save();

            $request = EvaluationRequest::query()->lockForUpdate()->find($order->evaluation_request_id);
            if ($request === null || $request->status !== EvaluationRequestStatus::AwaitingPayment) {
                return;
            }

            EvaluationRequest::query()->whereKey($request->getKey())->update([
                'status' => EvaluationRequestStatus::Paid->value,
                'paid_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);

            AuditLogger::record(
                event: 'evaluation_request.payment_confirmed',
                auditable: $request,
                before: ['status' => EvaluationRequestStatus::AwaitingPayment->value],
                after: ['status' => EvaluationRequestStatus::Paid->value],
                metadata: ['payment_id' => $payment->getKey(), 'order_id' => $order->getKey()],
            );
        });
    }

    /** @param array<string, mixed> $event */
    public function markCheckoutFailed(array $event): void
    {
        $this->updatePaymentFromCheckoutEvent($event, PaymentStatus::Failed, OrderStatus::Failed);
    }

    /** @param array<string, mixed> $event */
    public function markCheckoutExpired(array $event): void
    {
        $this->updatePaymentFromCheckoutEvent($event, PaymentStatus::Expired, OrderStatus::Cancelled);
    }

    /** @param array<string, mixed> $event */
    private function updatePaymentFromCheckoutEvent(array $event, PaymentStatus $paymentStatus, OrderStatus $orderStatus): void
    {
        $session = $event['data']['object'] ?? null;
        if (is_array($session) === false) {
            return;
        }

        $metadata = $session['metadata'] ?? null;
        if (is_array($metadata) === false) {
            return;
        }

        $paymentId = filter_var($metadata['payment_id'] ?? null, FILTER_VALIDATE_INT);
        $orderId = filter_var($metadata['order_id'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($paymentId) === false || is_int($orderId) === false) {
            return;
        }

        DB::transaction(function () use ($paymentId, $orderId, $event, $paymentStatus, $orderStatus): void {
            $payment = Payment::query()->lockForUpdate()->find($paymentId);
            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($payment === null || $order === null || $payment->order_id !== $order->getKey()) {
                return;
            }

            if ($payment->status === PaymentStatus::Paid || $order->status === OrderStatus::Paid) {
                return;
            }

            $payment->forceFill([
                'status' => $paymentStatus,
                'provider_metadata' => $event,
            ])->save();

            $order->forceFill(['status' => $orderStatus])->save();
        });
    }
}
