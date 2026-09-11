<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class AuditorAssignmentAccess
{
    public function __construct(private readonly AuditorEligibility $eligibility) {}

    /**
     * @return Builder<AuditorAssignment>
     */
    public function queryFor(User $user): Builder
    {
        if (! $this->eligibility->canAccessAssignments($user)) {
            return AuditorAssignment::query()->whereKey('__no_assignments__');
        }

        return AuditorAssignment::query()
            ->where('auditor_id', $user->getKey())
            ->whereHas('conflictDeclarations', function (Builder $query): void {
                $query->where('outcome', 'cleared')
                    ->whereNotNull('determined_at');
            })
            ->whereDoesntHave('conflictDeclarations', function (Builder $query): void {
                $query->whereNotNull('determined_at')
                    ->where('outcome', '!=', 'cleared');
            })
            ->with([
                'evaluation.product',
                'evaluation.productRelease',
                'evaluation.standardVersion',
                'evaluation.request',
            ])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('assigned_at');
    }

    public function findFor(User $user, string|int $assignmentId): AuditorAssignment
    {
        $assignment = $this->queryFor($user)->whereKey($assignmentId)->first();

        if ($assignment === null) {
            throw new AuthorizationException('You are not authorized to access this assignment.');
        }

        return $assignment;
    }

    public function isClearedAuditor(User $user): bool
    {
        return $this->eligibility->canAccessAssignments($user);
    }

    public function hasSubstantiveWorkAccess(AuditorAssignment $assignment): bool
    {
        $auditor = $assignment->auditor;

        return $this->eligibility->canStartSubstantiveWork($auditor, $assignment);
    }
}
