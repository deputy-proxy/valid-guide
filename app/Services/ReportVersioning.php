<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportVersioning
{
    public function createInitial(Evaluation $evaluation, User $createdBy): ReportVersion
    {
        $this->authorizePlatformAdmin($createdBy);

        return DB::transaction(function () use ($evaluation, $createdBy): ReportVersion {
            $evaluation = Evaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();

            if ($evaluation->status !== EvaluationStatus::Completed) {
                throw new DomainStateTransitionException('A report can only be created for a completed evaluation.');
            }

            if ($evaluation->decision === null) {
                throw new DomainStateTransitionException('A report requires a recorded evaluation decision.');
            }

            $report = Report::query()->firstOrCreate(
                ['evaluation_id' => $evaluation->id],
                [],
            );

            if ($report->versions()->exists()) {
                throw new DomainStateTransitionException('The evaluation already has a report version.');
            }

            $version = $report->versions()->create([
                'version_number' => 1,
                'content_structure' => $this->contentStructure($evaluation),
                'abstract' => $this->abstract($evaluation),
                'decision_snapshot' => [
                    'decision' => $evaluation->decision,
                    'overall_score' => $evaluation->overall_score,
                ],
                'standard_version_snapshot' => [
                    'id' => $evaluation->standard_version_id,
                ],
                'created_by' => $createdBy->id,
            ]);

            $now = now();
            Report::query()->whereKey($report->getKey())->update([
                'current_version_id' => $version->id,
                'creator_visible_at' => $now,
                'updated_at' => $now,
            ]);

            $report->refresh();

            AuditLogger::record(
                event: 'report.version_created',
                auditable: $version,
                after: [
                    'report_id' => $report->id,
                    'version_number' => 1,
                    'created_by' => $createdBy->id,
                ],
            );

            return $version->refresh();
        });
    }

    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    /** @param array<string,mixed> $contentStructure */
    public function createRevision(Report $report, User $createdBy, array $contentStructure, ?string $abstract, string $changeReason): ReportVersion
    {
        $this->authorizePlatformAdmin($createdBy);

        if (trim($changeReason) === '') {
            throw new DomainStateTransitionException('A report revision requires a change reason.');
        }

        return DB::transaction(function () use ($report, $createdBy, $contentStructure, $abstract, $changeReason): ReportVersion {
            $report = Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();

            if ($report->delivered_at !== null) {
                throw new DomainStateTransitionException('A delivered report cannot be revised; issue a new evaluation instead.');
            }

            $currentVersion = $report->currentVersion;
            if ($currentVersion === null) {
                throw new DomainStateTransitionException('A report revision requires an existing current version.');
            }

            $nextVersion = ((int) $report->versions()->max('version_number')) + 1;

            $version = $report->versions()->create([
                'version_number' => $nextVersion,
                'content_structure' => $contentStructure,
                'abstract' => $abstract,
                'decision_snapshot' => $currentVersion->decision_snapshot,
                'standard_version_snapshot' => $currentVersion->standard_version_snapshot,
                'created_by' => $createdBy->id,
                'change_reason' => $changeReason,
            ]);

            $now = now();
            Report::query()->whereKey($report->getKey())->update([
                'current_version_id' => $version->id,
                'updated_at' => $now,
            ]);

            $report->refresh();

            AuditLogger::record(
                event: 'report.version_created',
                auditable: $version,
                after: [
                    'report_id' => $report->id,
                    'version_number' => $nextVersion,
                    'created_by' => $createdBy->id,
                    'change_reason' => $changeReason,
                ],
            );

            return $version->refresh();
        });
    }

    private function authorizePlatformAdmin(User $user): void
    {
        if (! $user->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only a platform administrator can create or revise evaluation reports.');
        }
    }

    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    /** @return array<string,mixed> */
    private function contentStructure(Evaluation $evaluation): array
    {
        return [
            'decision' => $evaluation->decision,
            'overall_score' => $evaluation->overall_score,
            'generated_from' => 'evaluation_decision',
        ];
    }

    private function abstract(Evaluation $evaluation): string
    {
        $decision = $evaluation->decision === 'validated' ? 'validated' : 'not validated';
        $score = $evaluation->overall_score !== null ? sprintf(' with an overall score of %s/100', $evaluation->overall_score) : '';

        return sprintf('The evaluated product was %s against the frozen methodology%s.', $decision, $score);
    }
}
