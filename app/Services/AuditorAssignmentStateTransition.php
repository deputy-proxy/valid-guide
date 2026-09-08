<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorAssignment;
use Illuminate\Support\Facades\DB;

class AuditorAssignmentStateTransition
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'offered' => ['accepted', 'declined', 'cancelled'],
        'accepted' => ['completed', 'cancelled'],
        'declined' => [],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transition(AuditorAssignment $assignment, string $to): AuditorAssignment
    {
        $from = $assignment->status;

        if ($from === $to) {
            throw new DomainStateTransitionException('The auditor assignment is already in the requested state.');
        }

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainStateTransitionException(sprintf(
                'Invalid auditor assignment transition: %s -> %s.',
                $from,
                $to,
            ));
        }

        return DB::transaction(function () use ($assignment, $from, $to): AuditorAssignment {
            $assignment = AuditorAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($to === 'accepted') {
                $cleared = $assignment->conflictDeclarations()
                    ->where('outcome', 'cleared')
                    ->whereNotNull('determined_at')
                    ->exists();

                if (! $cleared) {
                    throw new DomainStateTransitionException(
                        'An auditor assignment cannot be accepted until its conflict declaration has been cleared.',
                    );
                }
            }

            $assignment->status = $to;
            $now = now();

            if ($to === 'accepted') {
                $assignment->accepted_at = $now;
            }

            if ($to === 'completed') {
                $assignment->completed_at = $now;
            }

            $assignment->save();

            AuditLogger::record(
                event: 'auditor_assignment.status_changed',
                auditable: $assignment,
                before: ['status' => $from],
                after: ['status' => $to],
            );

            return $assignment->refresh();
        });
    }
}
