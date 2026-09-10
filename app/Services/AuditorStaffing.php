<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;

class AuditorStaffing
{
    public function requiredCount(Evaluation $evaluation): int
    {
        $complexity = $evaluation->request->complexity;

        $standardVersion = $evaluation->standardVersion;
        if ($standardVersion === null) {
            throw new DomainStateTransitionException('An evaluation must have a methodology standard version before Auditor staffing can be determined.');
        }

        return $standardVersion->auditorCountFor($complexity);
    }

    public function staffedCount(Evaluation $evaluation): int
    {
        return $evaluation->assignments()
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->count();
    }

    public function submittedCount(Evaluation $evaluation): int
    {
        return $evaluation->auditorEvaluations()
            ->where('status', 'submitted')
            ->whereNotNull('locked_at')
            ->distinct()
            ->count('auditor_assignment_id');
    }

    public function assertCanAdd(Evaluation $evaluation): void
    {
        $required = $this->requiredCount($evaluation);
        $staffed = $this->staffedCount($evaluation);

        if ($staffed >= $required) {
            throw new DomainStateTransitionException(sprintf(
                'This evaluation already has the required %d Auditor%s for its %s complexity.',
                $required,
                $required === 1 ? '' : 's',
                $evaluation->request->complexity->value,
            ));
        }
    }

    public function assertFullyStaffed(Evaluation $evaluation): void
    {
        $required = $this->requiredCount($evaluation);
        $staffed = $this->staffedCount($evaluation);

        if ($staffed !== $required) {
            throw new DomainStateTransitionException(sprintf(
                'The evaluation requires exactly %d Auditor%s before Auditor work can begin, but %d %s currently staffed.',
                $required,
                $required === 1 ? '' : 's',
                $staffed,
                $staffed === 1 ? 'is' : 'are',
            ));
        }
    }

    public function assertSubmittedCount(Evaluation $evaluation): void
    {
        $required = $this->requiredCount($evaluation);
        $submitted = $this->submittedCount($evaluation);

        if ($submitted !== $required) {
            throw new DomainStateTransitionException(sprintf(
                'Final evaluation decisions require exactly %d submitted Auditor evaluation%s, but %d %s submitted.',
                $required,
                $required === 1 ? '' : 's',
                $submitted,
                $submitted === 1 ? 'is' : 'are',
            ));
        }
    }
}
