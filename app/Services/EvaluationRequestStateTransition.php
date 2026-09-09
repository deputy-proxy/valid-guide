<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use Illuminate\Support\Facades\DB;

class EvaluationRequestStateTransition
{
    /** @var array<string, list<EvaluationRequestStatus>> */
    private const TRANSITIONS = [
        'draft' => [EvaluationRequestStatus::AwaitingPayment],
        'awaiting_payment' => [EvaluationRequestStatus::Paid, EvaluationRequestStatus::Cancelled],
        'paid' => [EvaluationRequestStatus::Intake, EvaluationRequestStatus::Refunded],
        'intake' => [EvaluationRequestStatus::AwaitingCreator, EvaluationRequestStatus::Ready, EvaluationRequestStatus::Refunded],
        'awaiting_creator' => [EvaluationRequestStatus::Ready, EvaluationRequestStatus::Cancelled, EvaluationRequestStatus::Refunded],
        'ready' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function transition(EvaluationRequest $request, EvaluationRequestStatus $to): EvaluationRequest
    {
        return DB::transaction(function () use ($request, $to): EvaluationRequest {
            $request = EvaluationRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $from = $request->status;

            if ($from === $to) {
                throw new DomainStateTransitionException('The evaluation request is already in the requested state.');
            }

            if (! in_array($to, self::TRANSITIONS[$from->value] ?? [], true)) {
                throw new DomainStateTransitionException(sprintf(
                    'Invalid evaluation request transition: %s -> %s.',
                    $from->value,
                    $to->value,
                ));
            }

            if ($to === EvaluationRequestStatus::AwaitingPayment) {
                if ($request->service_package_id === null || $request->quoted_price === null || $request->currency === null) {
                    throw new DomainStateTransitionException(
                        'An evaluation request must have frozen commercial terms before payment can begin.',
                    );
                }
            }

            if ($to === EvaluationRequestStatus::Ready) {
                if (! $request->materials()->where('status', 'verified')->exists()) {
                    throw new DomainStateTransitionException(
                        'An evaluation request cannot become ready without at least one verified material.',
                    );
                }
            }

            if ($to === EvaluationRequestStatus::Refunded) {
                if ($request->evaluations()->whereHas('report', fn ($query) => $query->whereNotNull('delivered_at'))->exists()) {
                    throw new DomainStateTransitionException(
                        'An evaluation request cannot be refunded after a report has been delivered.',
                    );
                }
            }

            $now = now();
            $updates = [
                'status' => $to->value,
                'updated_at' => $now,
            ];

            match ($to) {
                EvaluationRequestStatus::AwaitingPayment => $updates['payment_started_at'] = $request->payment_started_at ?? $now,
                EvaluationRequestStatus::Paid => $updates['paid_at'] = $request->paid_at ?? $now,
                EvaluationRequestStatus::Cancelled => $updates['cancelled_at'] = $request->cancelled_at ?? $now,
                EvaluationRequestStatus::Refunded => $updates['refunded_at'] = $request->refunded_at ?? $now,
                default => null,
            };

            if ($to === EvaluationRequestStatus::AwaitingPayment && $request->submitted_at === null) {
                $updates['submitted_at'] = $now;
            }

            EvaluationRequest::query()
                ->whereKey($request->getKey())
                ->update($updates);

            $request->refresh();

            AuditLogger::record(
                event: 'evaluation_request.status_changed',
                auditable: $request,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
            );

            return $request;
        });
    }
}
