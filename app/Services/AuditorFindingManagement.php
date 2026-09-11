<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AuditorFindingManagement
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function create(
        User $user,
        AuditorEvaluation $auditorEvaluation,
        array $attributes,
    ): Finding {
        return DB::transaction(function () use ($user, $auditorEvaluation, $attributes): Finding {
            $evaluation = $this->assertDraftAccess($user, $auditorEvaluation);
            $validated = Validator::make($attributes, $this->rules())->validate();
            $criterion = $this->resolveCriterion($evaluation, $validated['criterion_id'] ?? null);

            $finding = Finding::query()->create([
                'evaluation_id' => $evaluation->evaluation_id,
                'criterion_id' => $criterion?->getKey(),
                'auditor_evaluation_id' => $evaluation->getKey(),
                'type' => $validated['type'],
                'severity' => $validated['severity'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => 'draft',
            ]);

            AuditLogger::record(
                event: 'auditor_finding.created',
                auditable: $finding,
                after: $this->auditData($finding),
                actor: $user,
            );

            return $finding->refresh();
        });
    }

    public function delete(User $user, Finding $finding): void
    {
        DB::transaction(function () use ($user, $finding): void {
            $auditorEvaluation = $finding->auditorEvaluation()->first();

            if ($auditorEvaluation === null) {
                throw new AuthorizationException('This finding is not attributable to an Auditor evaluation.');
            }

            $this->assertDraftAccess($user, $auditorEvaluation);

            if ((int) $auditorEvaluation->getKey() !== (int) $finding->auditor_evaluation_id) {
                throw new AuthorizationException('You are not authorized to delete this finding.');
            }

            $before = $this->auditData($finding);
            $finding->delete();

            AuditLogger::record(
                event: 'auditor_finding.deleted',
                auditable: $finding,
                before: $before,
                actor: $user,
            );
        });
    }

    private function assertDraftAccess(User $user, AuditorEvaluation $auditorEvaluation): AuditorEvaluation
    {
        $evaluation = AuditorEvaluation::query()
            ->whereKey($auditorEvaluation->getKey())
            ->with('assignment')
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $evaluation->assignment->auditor_id !== (int) $user->getKey()) {
            throw new AuthorizationException('You are not authorized to modify this Auditor evaluation.');
        }

        if ($evaluation->locked_at !== null || $evaluation->status !== 'draft') {
            throw new AuthorizationException('Submitted Auditor evaluations are immutable.');
        }

        if (! app(AuditorAssignmentAccess::class)->hasSubstantiveWorkAccess($evaluation->assignment)) {
            throw new AuthorizationException('You are not authorized to perform substantive work on this assignment.');
        }

        return $evaluation;
    }

    private function resolveCriterion(AuditorEvaluation $auditorEvaluation, mixed $criterionId): ?Criterion
    {
        if ($criterionId === null || $criterionId === '') {
            return null;
        }

        if (! is_int($criterionId) && ! (is_string($criterionId) && ctype_digit($criterionId))) {
            throw ValidationException::withMessages([
                'criterion_id' => 'The selected criterion is invalid.',
            ]);
        }

        $criterion = Criterion::query()
            ->whereKey((int) $criterionId)
            ->where('standard_version_id', $auditorEvaluation->evaluation()->value('standard_version_id'))
            ->first();

        if ($criterion === null) {
            throw ValidationException::withMessages([
                'criterion_id' => 'The selected criterion is not part of this evaluation standard version.',
            ]);
        }

        return $criterion;
    }

    /** @return array<string,array<int,string>> */
    private function rules(): array
    {
        return [
            'criterion_id' => ['nullable'],
            'type' => ['required', 'string', 'in,strength,weakness,risk,recommendation,factual_clarification'],
            'severity' => ['required', 'string', 'in,low,medium,high,critical'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
        ];
    }

    /** @return array<string,mixed> */
    private function auditData(Finding $finding): array
    {
        return [
            'evaluation_id' => $finding->evaluation_id,
            'auditor_evaluation_id' => $finding->auditor_evaluation_id,
            'criterion_id' => $finding->criterion_id,
            'type' => $finding->type,
            'severity' => $finding->severity,
            'title' => $finding->title,
            'status' => $finding->status,
        ];
    }
}
