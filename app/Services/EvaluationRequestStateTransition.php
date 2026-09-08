<?php

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

        return DB::transaction(function () use ($request, $from, $to): EvaluationRequest {
            $request->status = $to;
            $now = now();

            match ($to) {
                EvaluationRequestStatus::Paid => $request->paid_at = $now,
                EvaluationRequestStatus::Cancelled => $request->cancelled_at = $now,
                EvaluationRequestStatus::Refunded => $request->refunded_at = $now,
                default => null,
            };

            $request->save();

            AuditLogger::record(
                event: 'evaluation_request.status_changed',
                auditable: $request,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
            );

            return $request->refresh();
        });
    }
}
