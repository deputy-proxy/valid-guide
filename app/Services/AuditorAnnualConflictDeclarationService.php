<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditorAnnualConflictDeclarationService
{
    public function submit(User $auditor, string $disclosure = ''): AuditorAnnualConflictDeclaration
    {
        $year = (int) now()->year;

        return DB::transaction(function () use ($auditor, $year, $disclosure): AuditorAnnualConflictDeclaration {
            $existing = AuditorAnnualConflictDeclaration::query()
                ->where('auditor_id', $auditor->id)
                ->where('year', $year)
                ->first();

            if ($existing?->determined_at !== null) {
                throw new DomainStateTransitionException('A determined annual conflict declaration cannot be replaced.');
            }

            if ($existing !== null) {
                $existing->disclosure = $disclosure !== '' ? $disclosure : null;
                $existing->submitted_at = now();
                $existing->save();

                return $existing->refresh();
            }

            return AuditorAnnualConflictDeclaration::query()->create([
                'auditor_id' => $auditor->id,
                'year' => $year,
                'disclosure' => $disclosure !== '' ? $disclosure : null,
                'submitted_at' => now(),
            ]);
        });
    }

    public function determine(
        AuditorAnnualConflictDeclaration $declaration,
        User $determinedBy,
        string $outcome,
    ): AuditorAnnualConflictDeclaration {
        if (! $determinedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can determine annual conflict declarations.');
        }

        if (! in_array($outcome, ['cleared', 'disqualified'], true)) {
            throw new DomainStateTransitionException('Annual conflict outcome must be cleared or disqualified.');
        }

        return DB::transaction(function () use ($declaration, $determinedBy, $outcome): AuditorAnnualConflictDeclaration {
            $declaration = AuditorAnnualConflictDeclaration::query()
                ->whereKey($declaration->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($declaration->determined_at !== null) {
                throw new DomainStateTransitionException('The annual conflict determination is immutable.');
            }

            $declaration->outcome = $outcome;
            $declaration->determined_by = $determinedBy->id;
            $declaration->determined_at = now();
            $declaration->save();

            AuditLogger::record(
                event: 'auditor.annual_conflict_determined',
                auditable: $declaration,
                after: ['outcome' => $outcome, 'determined_by' => $determinedBy->id],
            );

            return $declaration->refresh();
        });
    }

    public function isCurrentAndCleared(User $auditor): bool
    {
        return AuditorAnnualConflictDeclaration::query()
            ->where('auditor_id', $auditor->id)
            ->where('year', now()->year)
            ->where('outcome', 'cleared')
            ->whereNotNull('determined_at')
            ->exists();
    }
}
