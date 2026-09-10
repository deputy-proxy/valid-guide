<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeRefundService
{
    /** @return array{provider_refund_id: string, status: string, metadata: array<string, mixed>} */
    public function refund(Refund $refund, Payment $payment): array
    {
        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $providerPaymentId = $payment->provider_payment_id;
        if ($providerPaymentId === null || $providerPaymentId === '') {
            throw new DomainStateTransitionException('A paid Stripe payment must have a provider payment identifier before it can be refunded.');
        }

        $response = Http::withToken($secret)
            ->withHeaders([
                'Idempotency-Key' => 'refund_'.$refund->getKey(),
            ])
            ->asForm()
            ->acceptJson()
            ->post(rtrim((string) config('stripe.api_url'), '/').'/v1/refunds', [
                'payment_intent' => $providerPaymentId,
                'amount' => (string) $refund->amount_minor,
                'metadata[refund_id]' => (string) $refund->getKey(),
                'metadata[order_id]' => (string) $refund->order_id,
                'metadata[payment_id]' => (string) $refund->payment_id,
                'metadata[evaluation_request_id]' => (string) $refund->evaluation_request_id,
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $data = $response->json();
        if (is_array($data) === false
            || ! isset($data['id'], $data['status'])
            || ! is_string($data['id'])
            || ! is_string($data['status'])) {
            throw new RuntimeException('Stripe returned an invalid refund response.');
        }

        /** @var array<string, mixed> $metadata */
        $metadata = $data;

        return [
            'provider_refund_id' => $data['id'],
            'status' => $data['status'],
            'metadata' => $metadata,
        ];
    }
}
