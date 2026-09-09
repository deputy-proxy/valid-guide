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
            $locked = collect($compensations)->map(fn (AuditorCompensation $compensation) =>
                AuditorCompensation::query()->with('assignment')->lockForUpdate()->findOrFail($compensation->id)
            );

            $auditorIds = $locked->map(fn (AuditorCompensation $c) => $c->assignment->auditor_id)->unique();
            $currencies = $locked->pluck('currency')->unique();

            if ($auditorIds->count() !== 1 || $currencies->count() !== 1) {
                throw new DomainStateTransitionException('A payout must contain compensations for one auditor and one currency.');
            }

            if ($locked->contains(fn (AuditorCompensation $c) => $c->status !== 'payable')) {
                throw new DomainStateTransitionException('Only payable auditor compensation can be included in a payout.');
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
            $payout = Payout::query()->with('items.compensation')->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== 'pending') {
                throw new DomainStateTransitionException('Only pending payouts can be marked as paid.');
            }

            if ($payout->items->isEmpty() || $payout->items->contains(fn ($item) => $item->compensation->status !== 'payable')) {
                throw new DomainStateTransitionException('A payout can only be paid when every compensation item is payable.');
            }

            $payout->status = 'paid';
            $payout->paid_at = now();
            $payout->payment_reference = $paymentReference;
            $payout->save();

            foreach ($payout->items as $item) {
                $compensation = $item->compensation;
                $compensation->status = 'paid';
                $compensation->paid_at = $payout->paid_at;
                $compensation->save();
            }

            AuditLogger::record(event: 'auditor.payout.paid', auditable: $payout, after: [
                'paid_at' => $payout->paid_at,
                'payment_reference' => $paymentReference,
                'paid_by' => $actor->id,
            ]);

            return $payout->refresh();
        });
    }
}
