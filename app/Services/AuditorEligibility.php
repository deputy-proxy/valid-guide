<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Models\AuditorAnnualConflictDeclaration;
use App\Models\AuditorAssignment;
use App\Models\User;

final class AuditorEligibility
{
    public function hasApprovedProfile(User $auditor): bool
    {

        return $auditor->auditorProfile?->status === AuditorProfileStatus::Approved;
    }

    public function hasCurrentAnnualClearance(User $auditor): bool
    {

        return AuditorAnnualConflictDeclaration::query()
            ->where('auditor_id', $auditor->getKey())
            ->where('year', now()->year)
            ->where('outcome', 'cleared')
            ->whereNotNull('determined_at')
            ->exists();
    }

    public function hasAssignmentClearance(AuditorAssignment $assignment): bool
    {

        return $assignment->conflictDeclarations()
            ->where('outcome', 'cleared')
            ->whereNotNull('determined_at')
            ->exists()
            && ! $assignment->conflictDeclarations()
            ->whereNotNull('determined_at')
            ->where('outcome', '!=', 'cleared')
            ->exists();
    }

    public function canAccessAssignments(User $auditor): bool
    {

        return $this->hasApprovedProfile($auditor) && $this->hasCurrentAnnualClearance($auditor);
    }

    public function canStartSubstantiveWork(User $auditor, AuditorAssignment $assignment): bool
    {

        return $this->canAccessAssignments($auditor)
            && (int) $assignment->auditor_id === (int) $auditor->getKey()
            && $this->hasAssignmentClearance($assignment)
            && in_array($assignment->status, ['accepted', 'cleared'], true);
    }
}
