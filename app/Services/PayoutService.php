<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorCompensation;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public function create(User $actor, AuditorCompensation ...$compensations): Payout
    {
        if (! $actor->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can create auditor payouts.');
        }

        if ($compensations === []) {
            throw new DomainStateTransitionException('A payout requires at least one compensation.');
        }

        return DB::transaction(function () use ($actor, $compensations): Payout {
            $ids = collect($compensations)->map(fn (AuditorCompensation $compensation) => (int) $compensation->getKey());

            if ($ids->count() !== $ids->unique()->count()) {
                throw new DomainStateTransitionException('A payout cannot contain the same compensation more than once.');
            }

            $locked = $ids->map(fn (int $id) => AuditorCompensation::query()
                ->with('assignment')
                ->lockForUpdate()
                ->findOrFail($id));

            if ($locked->contains(fn (AuditorCompensation $c) => $c->status !== 'payable')) {
                throw new DomainStateTransitionException('Only payable auditor compensation can be included in a payout.');
            }

            if ($locked->contains(fn (AuditorCompensation $c) => $c->assignment === null)) {
                throw new DomainStateTransitionException('Every compensation in a payout must have an auditor assignment.');
            }

            if ($locked->contains(fn (AuditorCompensation $c) => $c->payoutItems()->exists())) {
                throw new DomainStateTransitionException('A compensation can only belong to one payout.');
            }

            $auditorIds = $locked->map(fn (AuditorCompensation $c) => $c->assignment->auditor_id)->unique();
            $currencies = $locked->pluck('currency')->unique();

            if ($auditorIds->count() !== 1 || $currencies->count() !== 1) {
                throw new DomainStateTransitionException('A payout must contain compensations for one auditor and one currency.');
            }

            $amount = (int) $locked->sum('amount_minor');
            $payout = Payout::query()->create([
                'auditor_id' => $auditorIds->first(),
                'amount_minor' => $amount,
                'currency' => $currencies->first(),
                'status' => 'pending',
            ]);

            foreach ($locked as $compensation) {
                $payout->items()->create([
                    'auditor_compensation_id' => $compensation->id,
                    'amount_minor' => $compensation->amount_minor,
                ]);
            }

            AuditLogger::record(event: 'auditor.payout.created', auditable: $payout, after: [
                'auditor_id' => $payout->auditor_id,
                'amount_minor' => $amount,
                'currency' => $payout->currency,
                'created_by' => $actor->id,
            ]);

            return $payout->load('items')->refresh();
        });
    }

    public function markPaid(Payout $payout, User $actor, string $paymentReference): Payout
    {
        if (! $actor->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can mark auditor payouts as paid.');
        }

        if (trim($paymentReference) === '') {
            throw new DomainStateTransitionException('A paid payout requires a payment reference.');
        }

        return DB::transaction(function () use ($payout, $actor, $paymentReference): Payout {
            $payout = Payout::query()->with('items.compensation.assignment')->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== 'pending') {
                throw new DomainStateTransitionException('Only pending payouts can be marked as paid.');
            }

            if ($payout->items->isEmpty() || $payout->items->contains(fn ($item) => $item->compensation->status !== 'payable')) {
                throw new DomainStateTransitionException('A payout can only be paid when every compensation item is payable.');
            }

            $calculatedAmount = (int) $payout->items->sum(fn ($item) => $item->amount_minor);
            $auditorIds = $payout->items->map(fn ($item) => $item->compensation->assignment->auditor_id)->unique();
            $currencies = $payout->items->map(fn ($item) => $item->compensation->currency)->unique();

            if ($calculatedAmount !== (int) $payout->amount_minor || $auditorIds->count() !== 1 || $auditorIds->first() !== $payout->auditor_id || $currencies->count() !== 1 || $currencies->first() !== $payout->currency) {
                throw new DomainStateTransitionException('Payout totals and identity do not match their immutable compensation items.');
            }

            $paidAt = now();
            Payout::query()->whereKey($payout->id)->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'payment_reference' => $paymentReference,
                'updated_at' => $paidAt,
            ]);

            foreach ($payout->items as $item) {
                $compensation = $item->compensation;
                $compensation->status = 'paid';
                $compensation->paid_at = $paidAt;
                $compensation->save();
            }

            $payout->refresh();

            AuditLogger::record(event: 'auditor.payout.paid', auditable: $payout, after: [
                'paid_at' => $payout->paid_at,
                'payment_reference' => $paymentReference,
                'paid_by' => $actor->id,
            ]);

            return $payout;
        });
    }
}
