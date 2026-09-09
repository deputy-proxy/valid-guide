<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorAssignment;
use App\Models\AuditorCompensation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditorCompensationService
{
    public function assign(
        AuditorAssignment $assignment,
        User $actor,
        int $amountMinor,
        string $currency,
    ): AuditorCompensation {
        if (! $actor->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can assign auditor compensation.');
        }

        if ($amountMinor <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainStateTransitionException('Auditor compensation requires a positive amount and ISO currency code.');
        }

        return DB::transaction(function () use ($assignment, $amountMinor, $currency, $actor): AuditorCompensation {
            $assignment = AuditorAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($assignment->compensation()->exists()) {
                throw new DomainStateTransitionException('Auditor compensation has already been assigned for this assignment.');
            }

            $compensation = AuditorCompensation::query()->create([
                'auditor_assignment_id' => $assignment->id,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => 'pending',
            ]);

            $assignment->compensation_amount_minor = $amountMinor;
            $assignment->compensation_currency = $currency;
            $assignment->compensation_status = 'pending';
            $assignment->save();

            AuditLogger::record(
                event: 'auditor.compensation_assigned',
                auditable: $compensation,
                after: ['assignment_id' => $assignment->id, 'amount_minor' => $amountMinor, 'currency' => $currency, 'assigned_by' => $actor->id],
            );

            return $compensation->refresh();
        });
    }

    public function finalize(AuditorCompensation $compensation): AuditorCompensation
    {
        return DB::transaction(function () use ($compensation): AuditorCompensation {
            $compensation = AuditorCompensation::query()->with('assignment')->lockForUpdate()->findOrFail($compensation->id);
            $assignment = AuditorAssignment::query()->lockForUpdate()->findOrFail($compensation->auditor_assignment_id);

            if ($compensation->status !== 'pending') {
                throw new DomainStateTransitionException('Only pending auditor compensation can be finalized.');
            }

            if ($assignment->status !== 'completed' || $assignment->completed_at === null) {
                throw new DomainStateTransitionException('Auditor compensation becomes payable only after assignment completion.');
            }

            if ($assignment->due_at !== null && $assignment->completed_at->isAfter($assignment->due_at)) {
                $compensation->status = 'forfeited';
                $compensation->forfeited_at = now();
                $compensation->status_reason = 'Assignment completed after its deadline.';
                $compensation->save();

                $assignment->compensation_status = 'forfeited';
                $assignment->save();

                AuditLogger::record(event: 'auditor.compensation.forfeited', auditable: $compensation, after: ['reason' => $compensation->status_reason]);

                return $compensation->refresh();
            }

            $compensation->status = 'payable';
            $compensation->payable_at = now();
            $compensation->save();

            $assignment->compensation_status = 'payable';
            $assignment->save();

            AuditLogger::record(event: 'auditor.compensation.payable', auditable: $compensation, after: ['payable_at' => $compensation->payable_at]);

            return $compensation->refresh();
        });
    }
}
