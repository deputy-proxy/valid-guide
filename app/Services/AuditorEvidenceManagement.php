<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditorEvaluation;
use App\Models\Criterion;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AuditorEvidenceManagement
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        User $user,
        AuditorEvaluation $auditorEvaluation,
        array $attributes,
    ): Evidence {
        return DB::transaction(function () use ($user, $auditorEvaluation, $attributes): Evidence {
            $evaluation = $this->assertDraftAccess($user, $auditorEvaluation);
            $validated = Validator::make($attributes, $this->rules())->validate();
            $criterion = $this->resolveCriterion($evaluation, $validated['criterion_id'] ?? null);

            $evidence = Evidence::query()->create([
                'evaluation_id' => $evaluation->evaluation_id,
                'auditor_evaluation_id' => $evaluation->getKey(),
                'criterion_result_id' => $criterion?->results()
                    ->where('auditor_evaluation_id', $evaluation->getKey())
                    ->value('id'),
                'type' => $validated['type'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'source_url' => $validated['source_url'] ?? null,
                'storage_path' => null,
                'provenance' => $validated['provenance'],
                'captured_at' => now(),
                'visibility' => 'private',
            ]);

            AuditLogger::record(
                event: 'auditor_evidence.created',
                auditable: $evidence,
                after: $this->auditData($evidence),
                actor: $user,
            );

            return $evidence->refresh();
        });
    }

    public function delete(User $user, Evidence $evidence): void
    {
        DB::transaction(function () use ($user, $evidence): void {
            $auditorEvaluation = $evidence->auditorEvaluation()->first();

            if ($auditorEvaluation === null) {
                throw new AuthorizationException('This evidence reference is not attributable to an Auditor evaluation.');
            }

            $this->assertDraftAccess($user, $auditorEvaluation);

            if ((int) $auditorEvaluation->getKey() !== (int) $evidence->auditor_evaluation_id) {
                throw new AuthorizationException('You are not authorized to delete this evidence reference.');
            }

            $before = $this->auditData($evidence);
            $evidence->delete();

            AuditLogger::record(
                event: 'auditor_evidence.deleted',
                auditable: $evidence,
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

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'criterion_id' => ['nullable'],
            'type' => ['required', 'string', 'in:observation,document,link,reference'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'provenance' => ['required', 'string', 'in:observed,creator_supplied,external,professional_judgement'],
        ];
    }

    /** @return array<string, mixed> */
    private function auditData(Evidence $evidence): array
    {
        return [
            'evaluation_id' => $evidence->evaluation_id,
            'auditor_evaluation_id' => $evidence->auditor_evaluation_id,
            'criterion_result_id' => $evidence->criterion_result_id,
            'type' => $evidence->type,
            'title' => $evidence->title,
            'provenance' => $evidence->provenance,
            'visibility' => $evidence->visibility,
        ];
    }
}
