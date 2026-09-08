<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConflictDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConflictDeclarationDecision
{
    public function decide(ConflictDeclaration $declaration, string $outcome, User $decidedBy): ConflictDeclaration
    {
        if (! $decidedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only a platform administrator can determine a conflict declaration.');
        }

        if (! in_array($outcome, ['cleared', 'disqualified'], true)) {
            throw new DomainStateTransitionException('A conflict declaration can only be cleared or disqualified.');
        }

        return DB::transaction(function () use ($declaration, $outcome, $decidedBy): ConflictDeclaration {
            $declaration = ConflictDeclaration::query()
                ->whereKey($declaration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($declaration->determined_at !== null) {
                throw new DomainStateTransitionException('A conflict declaration decision is immutable once determined.');
            }

            if ($declaration->assignment()->doesntExist()) {
                throw new DomainStateTransitionException('A conflict declaration must belong to an auditor assignment.');
            }

            $determinedAt = now();

            $declaration->outcome = $outcome;
            $declaration->determined_by = $decidedBy->getKey();
            $declaration->determined_at = $determinedAt;
            $declaration->save();

            AuditLogger::record(
                event: 'conflict_declaration.determined',
                auditable: $declaration,
                after: [
                    'outcome' => $outcome,
                    'determined_by' => $decidedBy->getKey(),
                    'determined_at' => $determinedAt->toIso8601String(),
                ],
            );

            return $declaration->refresh();
        });
    }
}
