<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EvaluationRequestStateTransition
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

    /**
     * Transition an evaluation request through its controlled lifecycle.
     *
     * Creator-controlled transitions are authorized against the request's
     * organization. Operational transitions remain controlled by this service
     * and are intended for payment/intake workflows until platform-admin
     * authorization is introduced.
     */
    public function transition(EvaluationRequest $request, EvaluationRequestStatus $to, User $actor): EvaluationRequest
    {
        return DB::transaction(function () use ($request, $to, $actor): EvaluationRequest {
            $request = EvaluationRequest::query()
                ->with('product.organization', 'productRelease.product.organization')
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var EvaluationRequest $request */
            $from = $request->status;

            if ($from === null) {
                throw new DomainStateTransitionException('An evaluation request must have a lifecycle state before it can transition.');
            }

            if ($from === $to) {
                throw new DomainStateTransitionException('The evaluation request is already in the requested state.');
            }

            if (! in_array($to, self::TRANSITIONS[$from->value], true)) {
                throw new DomainStateTransitionException(sprintf(
                    'Invalid evaluation request transition: %s -> %s.',
                    $from->value,
                    $to->value,
                ));
            }

            if (in_array($to, [EvaluationRequestStatus::AwaitingPayment, EvaluationRequestStatus::Cancelled], true)) {
                Gate::forUser($actor)->authorize($this->ability($to), $request);
            }

            if ($to === EvaluationRequestStatus::AwaitingPayment) {
                $this->assertPaymentBoundary($request);
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
                actor: $actor,
            );

            return $request;
        });
    }

    private function assertPaymentBoundary(EvaluationRequest $request): void
    {
        if ($request->organization_id === null || $request->product_id === null || $request->product_release_id === null) {
            throw new DomainStateTransitionException(
                'An evaluation request must identify an organization, product and exact product release before payment can begin.',
            );
        }

        $product = $request->product;
        $release = $request->productRelease;

        if ($product === null || $release === null || $product->organization_id !== $request->organization_id) {
            throw new DomainStateTransitionException(
                'An evaluation request product must belong to the requested organization.',
            );
        }

        if ($release->product_id !== $product->getKey()) {
            throw new DomainStateTransitionException(
                'An evaluation request product release must belong to the requested product.',
            );
        }

        if ($request->service_package_id === null || $request->quoted_price === null || blank($request->currency)) {
            throw new DomainStateTransitionException(
                'An evaluation request must have frozen commercial terms before payment can begin.',
            );
        }

        if (blank($request->service_package_name_snapshot) || blank($request->service_package_description_snapshot)) {
            throw new DomainStateTransitionException(
                'An evaluation request must retain the service package snapshot before payment can begin.',
            );
        }
    }

    private function ability(EvaluationRequestStatus $to): string
    {
        return match ($to) {
            EvaluationRequestStatus::AwaitingPayment => 'submit',
            EvaluationRequestStatus::Cancelled => 'cancel',
            default => throw new DomainStateTransitionException('The requested transition is not creator-controlled.'),
        };
    }
}
