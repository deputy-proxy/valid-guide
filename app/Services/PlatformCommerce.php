<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\EvaluationRequest;
use App\Models\Payment;

final class PlatformCommerce
{
    public function latestPayment(EvaluationRequest $request): ?Payment
    {
        return $request->order?->latestPayment;
    }

    public function refundEligible(EvaluationRequest $request): bool
    {
        if (! in_array($request->status, [
            EvaluationRequestStatus::Paid,
            EvaluationRequestStatus::Intake,
            EvaluationRequestStatus::AwaitingCreator,
            EvaluationRequestStatus::Ready,
        ], true)) {
            return false;
        }

        $order = $request->order;
        $payment = $this->latestPayment($request);
        $refund = $order?->refund;

        if ($order === null || $payment === null) {
            return false;
        }

        if ($order->status !== OrderStatus::Paid || $payment->status !== PaymentStatus::Paid) {
            return false;
        }

        if ($refund?->status === RefundStatus::Succeeded) {
            return false;
        }

        return ! $request->evaluations()
            ->whereHas('report', fn ($query) => $query->whereNotNull('delivered_at'))
            ->exists();
    }

    public function paymentRetryable(EvaluationRequest $request): bool
    {
        if ($request->status !== EvaluationRequestStatus::AwaitingPayment) {
            return false;
        }

        $order = $request->order;
        $payment = $this->latestPayment($request);

        if ($order === null || $payment === null) {
            return false;
        }

        return in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Expired], true)
            || in_array($order->status, [OrderStatus::Failed, OrderStatus::Cancelled], true);
    }
}
