<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorAssignment;
use App\Models\ConflictDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AuditorConflictDeclarationService
{
    public function submit(AuditorAssignment $assignment, User $auditor, string $disclosure): ConflictDeclaration
    {
        if ((int) $assignment->auditor_id !== (int) $auditor->getKey()) {
            throw new DomainStateTransitionException('An Auditor can only declare a conflict for their own assignment.');
        }

        $disclosure = trim($disclosure);

        if ($disclosure === '') {
            throw new DomainStateTransitionException('An assignment conflict declaration requires a disclosure.');
        }

        return DB::transaction(function () use ($assignment, $disclosure): ConflictDeclaration {
            $assignment = AuditorAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $assignment->conflictDeclarations()
                ->where('declaration_type', 'assignment')
                ->latest('id')
                ->first();

            if ($existing?->determined_at !== null) {
                throw new DomainStateTransitionException('A determined assignment conflict declaration cannot be replaced.');
            }

            if ($existing !== null) {
                $existing->disclosure = $disclosure;
                $existing->save();

                return $existing->refresh();
            }

            return ConflictDeclaration::query()->create([
                'evaluation_id' => $assignment->evaluation_id,
                'auditor_assignment_id' => $assignment->getKey(),
                'declaration_type' => 'assignment',
                'disclosure' => $disclosure,
            ]);
        });
    }
}
