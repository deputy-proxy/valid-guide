<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentWebhookEvent;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StripeWebhookController
{
    public function __invoke(Request $request, StripePaymentService $payments): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $this->isValidSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_BAD_REQUEST);
        }

        $event = json_decode($payload, true);
        if (! is_array($event) || ! isset($event['id'], $event['type']) || ! is_string($event['id']) || ! is_string($event['type'])) {
            return response()->json(['message' => 'Invalid webhook payload.'], Response::HTTP_BAD_REQUEST);
        }

        $webhookEvent = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $event['id']],
            [
                'provider' => 'stripe',
                'event_type' => $event['type'],
            ],
        );

        if ($webhookEvent->processed_at !== null) {
            return response()->json(['received' => true]);
        }

        $metadata = data_get($event, 'data.object.metadata');
        if (is_array($metadata) && isset($metadata['payment_id']) && is_numeric($metadata['payment_id'])) {
            $webhookEvent->forceFill(['payment_id' => (int) $metadata['payment_id']])->save();
        }

        try {
            match ($event['type']) {
                'checkout.session.completed' => $payments->confirmCheckoutPayment($event),
                'checkout.session.async_payment_succeeded' => $payments->confirmCheckoutPayment($event),
                'checkout.session.async_payment_failed' => $payments->markCheckoutFailed($event),
                'checkout.session.expired' => $payments->markCheckoutExpired($event),
                default => null,
            };

            $webhookEvent->forceFill(['processed_at' => now()])->save();
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Webhook processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['received' => true]);
    }

    private function isValidSignature(string $payload, ?string $signature): bool
    {
        $secret = config('stripe.webhook_secret');
        if (! is_string($secret) || $secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key === 't' && is_string($value)) {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            }
            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $tolerance = (int) config('stripe.webhook_tolerance', 300);
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
