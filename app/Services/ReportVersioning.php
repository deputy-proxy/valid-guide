<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Report;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportVersioning
{
    public function createInitial(Evaluation $evaluation, User $createdBy): ReportVersion
    {
        return DB::transaction(function () use ($evaluation, $createdBy): ReportVersion {
            $evaluation = Evaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();

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

            $report->current_version_id = $version->id;
            $report->creator_visible_at = now();
            $report->save();

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

    public function createRevision(Report $report, User $createdBy, array $contentStructure, ?string $abstract, string $changeReason): ReportVersion
    {
        if (trim($changeReason) === '') {
            throw new DomainStateTransitionException('A report revision requires a change reason.');
        }

        return DB::transaction(function () use ($report, $createdBy, $contentStructure, $abstract, $changeReason): ReportVersion {
            $report = Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $nextVersion = ((int) $report->versions()->max('version_number')) + 1;

            $version = $report->versions()->create([
                'version_number' => $nextVersion,
                'content_structure' => $contentStructure,
                'abstract' => $abstract,
                'decision_snapshot' => $report->currentVersion?->decision_snapshot,
                'standard_version_snapshot' => $report->currentVersion?->standard_version_snapshot,
                'created_by' => $createdBy->id,
                'change_reason' => $changeReason,
            ]);

            $report->current_version_id = $version->id;
            $report->save();

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
