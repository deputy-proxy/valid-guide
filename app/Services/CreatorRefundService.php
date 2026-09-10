<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\EvaluationRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class CreatorRefundService
{
    public function __construct(private readonly StripeRefundService $stripe) {}

    public function refund(EvaluationRequest $request, User $actor, ?string $reason = null): Refund
    {
        Gate::forUser($actor)->authorize('refund', $request);

        [$refund, $payment] = DB::transaction(function () use ($request, $actor, $reason): array {
            $request = EvaluationRequest::query()
                ->with('organization')
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('refund', $request);

            if ($request->organization_id === null) {
                throw new DomainStateTransitionException('A refund requires an evaluation request organization.');
            }

            $order = Order::query()
                ->where('evaluation_request_id', $request->getKey())
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new DomainStateTransitionException('A paid evaluation request must have an order before it can be refunded.');
            }

            if ($order->organization_id !== $request->organization_id) {
                throw new DomainStateTransitionException('The order organization does not match the evaluation request organization.');
            }

            $payment = Payment::query()
                ->where('order_id', $order->getKey())
                ->where('status', PaymentStatus::Paid)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new DomainStateTransitionException('A refund requires a paid payment.');
            }

            if ($order->status !== OrderStatus::Paid) {
                throw new DomainStateTransitionException('A refund requires a paid order.');
            }

            if ($payment->provider !== 'stripe' || $order->provider !== 'stripe') {
                throw new DomainStateTransitionException('Only Stripe payments can currently be refunded.');
            }

            if ($payment->amount_minor !== $order->amount_minor
                || strtoupper($payment->currency) !== strtoupper($order->currency)
                || $payment->amount_minor !== $request->quoted_amount_minor
                || strtoupper($payment->currency) !== strtoupper((string) $request->currency)) {
                throw new DomainStateTransitionException('The payment does not match the frozen commercial terms.');
            }

            if ($request->evaluations()->whereHas('report', fn ($query) => $query->whereNotNull('delivered_at'))->exists()) {
                throw new DomainStateTransitionException('An evaluation request cannot be refunded after a report has been delivered.');
            }

            $refund = Refund::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($refund?->status === RefundStatus::Succeeded) {
                return [$refund, $payment];
            }

            if ($refund === null) {
                $refund = Refund::query()->create([
                    'order_id' => $order->getKey(),
                    'payment_id' => $payment->getKey(),
                    'evaluation_request_id' => $request->getKey(),
                    'actor_id' => $actor->getKey(),
                    'amount_minor' => $payment->amount_minor,
                    'currency' => strtoupper($payment->currency),
                    'status' => RefundStatus::Pending,
                    'provider' => $payment->provider,
                    'reason' => $reason,
                    'requested_at' => now(),
                ]);
            }

            $refund->forceFill([
                'status' => RefundStatus::Processing,
                'failed_at' => null,
            ])->save();

            return [$refund, $payment];
        });

        if ($refund->status === RefundStatus::Succeeded) {
            return $refund;
        }

        try {
            $providerResult = $this->stripe->refund($refund, $payment);
        } catch (Throwable $exception) {
            $this->markFailed($refund, $exception->getMessage());

            throw $exception;
        }

        if ($providerResult['status'] === 'pending') {
            return DB::transaction(function () use ($refund, $providerResult): Refund {
                $refund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
                if ($refund->status !== RefundStatus::Succeeded) {
                    $refund->forceFill([
                        'status' => RefundStatus::Processing,
                        'provider_refund_id' => $providerResult['provider_refund_id'],
                        'provider_metadata' => $providerResult['metadata'],
                    ])->save();
                }

                return $refund;
            });
        }

        if ($providerResult['status'] !== 'succeeded') {
            $this->markFailed($refund, 'Stripe returned refund status: '.$providerResult['status'].'.');

            throw new DomainStateTransitionException('The Stripe refund was not completed. The refund remains recoverable and the payment has not been marked as refunded.');
        }

        return DB::transaction(function () use ($refund, $providerResult): Refund {
            $refund = Refund::query()
                ->whereKey($refund->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status === RefundStatus::Succeeded) {
                return $refund;
            }

            $request = EvaluationRequest::query()
                ->whereKey($refund->evaluation_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->evaluations()->whereHas('report', fn ($query) => $query->whereNotNull('delivered_at'))->exists()) {
                throw new DomainStateTransitionException('An evaluation request cannot be refunded after a report has been delivered.');
            }

            $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $fromStatus = $request->status;
            if ($fromStatus instanceof EvaluationRequestStatus === false) {
                throw new DomainStateTransitionException('The evaluation request must have a lifecycle state before it can be refunded.');
            }

            if (in_array($fromStatus, [
                EvaluationRequestStatus::Paid,
                EvaluationRequestStatus::Intake,
                EvaluationRequestStatus::AwaitingCreator,
                EvaluationRequestStatus::Ready,
                EvaluationRequestStatus::Refunded,
            ], true) === false) {
                throw new DomainStateTransitionException('The evaluation request is not in a refundable lifecycle state.');
            }

            $now = now();

            $refund->forceFill([
                'status' => RefundStatus::Succeeded,
                'provider_refund_id' => $providerResult['provider_refund_id'],
                'processed_at' => $now,
                'failed_at' => null,
                'provider_metadata' => $providerResult['metadata'],
            ])->save();

            Payment::query()->whereKey($payment->getKey())->update([
                'status' => PaymentStatus::Refunded->value,
                'updated_at' => $now,
            ]);

            Order::query()->whereKey($order->getKey())->update([
                'status' => OrderStatus::Refunded->value,
                'updated_at' => $now,
            ]);

            if ($fromStatus !== EvaluationRequestStatus::Refunded) {
                EvaluationRequest::query()->whereKey($request->getKey())->update([
                    'status' => EvaluationRequestStatus::Refunded->value,
                    'refunded_at' => $now,
                    'updated_at' => $now,
                ]);

                AuditLogger::record(
                    event: 'evaluation_request.status_changed',
                    auditable: $request,
                    before: ['status' => $fromStatus->value],
                    after: ['status' => EvaluationRequestStatus::Refunded->value],
                    metadata: [
                        'refund_id' => $refund->getKey(),
                        'payment_id' => $payment->getKey(),
                        'order_id' => $order->getKey(),
                    ],
                    actor: $refund->actor()->first(),
                );
            }

            AuditLogger::record(
                event: 'refund.succeeded',
                auditable: $refund,
                after: [
                    'status' => RefundStatus::Succeeded->value,
                    'amount_minor' => $refund->amount_minor,
                    'currency' => $refund->currency,
                    'provider_refund_id' => $refund->provider_refund_id,
                ],
                actor: $refund->actor()->first(),
            );

            return $refund;
        });
    }

    private function markFailed(Refund $refund, string $message): void
    {
        DB::transaction(function () use ($refund, $message): void {
            $refund = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->first();
            if ($refund === null || $refund->status === RefundStatus::Succeeded) {
                return;
            }

            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failed_at' => now(),
                'provider_metadata' => [
                    'error' => $message,
                ],
            ])->save();

            AuditLogger::record(
                event: 'refund.failed',
                auditable: $refund,
                after: [
                    'status' => RefundStatus::Failed->value,
                    'error' => $message,
                ],
                actor: $refund->actor()->first(),
            );
        });
    }
}
