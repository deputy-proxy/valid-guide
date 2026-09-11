<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CriterionAssessment;
use App\Models\AuditorAssignment;
use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\CriterionResult;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AuditorEvaluationWorkspace
{
    public function __construct(
        private readonly AuditorAssignmentAccess $assignmentAccess,
    ) {}

    public function findFor(User $user, string|int $assignmentId): AuditorEvaluation
    {
        $assignment = $this->assignmentAccess->findFor($user, $assignmentId);

        if (! $this->assignmentAccess->hasSubstantiveWorkAccess($assignment)) {
            throw new AuthorizationException('You are not authorized to perform substantive work on this assignment.');
        }

        $evaluation = AuditorEvaluation::query()
            ->where('auditor_assignment_id', $assignment->getKey())
            ->with([
                'evaluation.product',
                'evaluation.productRelease',
                'evaluation.request',
                'evaluation.standardVersion',
                'evaluation.standardVersion.criteria' => fn ($query) => $query->orderBy('sequence'),
                'evaluation.standardVersion.criteria.guidance',
                'criterionResults',
                'evidence',
            ])
            ->latest('version')
            ->first();

        if ($evaluation === null) {
            throw new AuthorizationException('No Auditor evaluation is available for this assignment.');
        }

        return $evaluation;
    }

    /** @return Collection<int, Criterion> */
    public function criteria(AuditorEvaluation $evaluation): Collection
    {
        return $evaluation->evaluation->standardVersion->criteria
            ->sortBy('sequence')
            ->values();
    }

    /** @return array<int,CriterionResult> */
    public function resultMap(AuditorEvaluation $evaluation): array
    {
        return $evaluation->criterionResults
            ->keyBy('criterion_id')
            ->all();
    }

    public function saveDraft(
        User $user,
        AuditorEvaluation $evaluation,
        int $criterionId,
        string $assessment,
        ?float $score,
        string $rationale,
        ?float $confidence,
    ): CriterionResult {
        return DB::transaction(function () use ($user, $evaluation, $criterionId, $assessment, $score, $rationale, $confidence): CriterionResult {
            $lockedEvaluation = AuditorEvaluation::query()
                ->whereKey($evaluation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $assignment = $lockedEvaluation->assignment()->lockForUpdate()->firstOrFail();

            if ((int) $assignment->auditor_id !== (int) $user->getKey()
                || ! $this->assignmentAccess->hasSubstantiveWorkAccess($assignment)) {
                throw new AuthorizationException('You are not authorized to modify this evaluation.');
            }

            if ($lockedEvaluation->locked_at !== null || $lockedEvaluation->status !== 'draft') {
                throw new AuthorizationException('Submitted Auditor evaluations are immutable.');
            }

            $assessmentEnum = CriterionAssessment::tryFrom($assessment);

            if ($assessmentEnum === null) {
                throw ValidationException::withMessages([
                    'assessment' => 'Select a valid methodology assessment.',
                ]);
            }

            $criterion = Criterion::query()
                ->whereKey($criterionId)
                ->where('standard_version_id', $lockedEvaluation->evaluation()->value('standard_version_id'))
                ->first();

            if ($criterion === null) {
                throw ValidationException::withMessages([
                    'criterionId' => 'The selected criterion is not part of this evaluation standard version.',
                ]);
            }

            if ($score !== null && ($score < 0 || $score > 100)) {
                throw ValidationException::withMessages([
                    'score' => 'The score must be between 0 and 100.',
                ]);
            }

            if ($confidence !== null && ($confidence < 0 || $confidence > 100)) {
                throw ValidationException::withMessages([
                    'confidence' => 'Confidence must be between 0 and 100.',
                ]);
            }

            if (! $assessmentEnum->isScored() && $score !== null) {
                throw ValidationException::withMessages([
                    'score' => 'This assessment does not accept a numerical score.',
                ]);
            }

            if ($assessmentEnum->isScored() && $score === null) {
                throw ValidationException::withMessages([
                    'score' => 'A numerical score is required for this assessment.',
                ]);
            }

            $result = CriterionResult::query()->firstOrNew([
                'auditor_evaluation_id' => $lockedEvaluation->getKey(),
                'criterion_id' => $criterion->getKey(),
            ]);

            $result->fill([
                'assessment' => $assessmentEnum,
                'score' => $score,
                'rationale' => $rationale,
                'confidence' => $confidence,
            ]);
            $result->save();

            return $result->refresh();
        });
    }
}
