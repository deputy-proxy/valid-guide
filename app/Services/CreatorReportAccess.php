<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditorEvaluation;
use App\Models\Evaluation;
use App\Models\Finding;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

final class CreatorReportAccess
{
    /** @return array<string, mixed> */
    public function show(User $user, int $evaluationId): array
    {
        $evaluation = Evaluation::query()
            ->with([
                'request.organization',
                'productRelease.product',
                'standardVersion.standard',
                'report.currentVersion',
                'report.versions',
                'validation.badge',
                'validation.publicVerificationRecord',
                'clarificationRequests',
                'disputes',
                'auditorEvaluations.criterionResults.criterion',
                'findings.criterion',
                'findings.auditorEvaluation',
            ])
            ->findOrFail($evaluationId);

        $organization = $evaluation->request?->organization;
        if ($organization === null) {
            throw new AuthorizationException('You are not authorized to access this creator report.');
        }

        if ($this->canAccess($user, $organization->id) === false) {
            throw new AuthorizationException('You are not authorized to access this creator report.');
        }

        if ($evaluation->status !== EvaluationStatus::Completed) {
            throw new AuthorizationException('This evaluation is not yet available to creators.');
        }

        $report = $evaluation->report;
        if ($report === null || $report->creator_visible_at === null) {
            throw new AuthorizationException('This report is not available to creators.');
        }

        $currentVersion = $report->currentVersion;

        return [
            'evaluation' => [
                'id' => $evaluation->getKey(),
                'status' => $this->enumValue($evaluation->getAttribute('status')),
                'decision' => (string) $evaluation->getAttribute('decision'),
                'overall_score' => $evaluation->getAttribute('overall_score'),
                'completed_at' => $this->dateString($evaluation->getAttribute('completed_at')),
            ],
            'organization' => [
                'id' => $organization->getKey(),
                'name' => $organization->name,
            ],
            'product' => $evaluation->productRelease?->product === null ? null : [
                'id' => $evaluation->productRelease->product->getKey(),
                'name' => $evaluation->productRelease->product->getAttribute('name'),
            ],
            'release' => $evaluation->productRelease === null ? null : [
                'id' => $evaluation->productRelease->getKey(),
                'version' => $evaluation->productRelease->version,
            ],
            'standard' => $evaluation->standardVersion === null ? null : [
                'id' => $evaluation->standardVersion->getKey(),
                'name' => $evaluation->standardVersion->standard?->name,
                'version' => $evaluation->standardVersion->version,
            ],
            'criteria' => $this->creatorCriteria($evaluation),
            'report' => [
                'id' => $report->getKey(),
                'current_version_id' => $currentVersion?->getKey(),
                'current' => $currentVersion === null ? null : $this->reportVersion($currentVersion),
                'versions' => $report->versions
                    ->sortByDesc('version_number')
                    ->map(fn (ReportVersion $version): array => $this->reportVersion($version))
                    ->values()
                    ->all(),
            ],
            'findings' => $this->creatorFindings($evaluation),
            'strengths' => $this->creatorFindingsByType($evaluation, ['strength', 'strengths']),
            'weaknesses' => $this->creatorFindingsByType($evaluation, ['weakness', 'weaknesses']),
            'validation' => $this->validation($evaluation),
            'clarifications' => $evaluation->clarificationRequests
                ->map(fn ($clarification): array => [
                    'id' => $clarification->getKey(),
                    'type' => (string) $clarification->getAttribute('type'),
                    'status' => (string) $clarification->getAttribute('status'),
                    'message' => $clarification->getAttribute('message'),
                    'response' => $clarification->getAttribute('response'),
                    'created_at' => $this->dateString($clarification->getAttribute('created_at')),
                    'responded_at' => $this->dateString($clarification->getAttribute('responded_at')),
                ])
                ->values()
                ->all(),
            'disputes' => $evaluation->disputes
                ->map(fn ($dispute): array => [
                    'id' => $dispute->getKey(),
                    'status' => (string) $dispute->getAttribute('status'),
                    'statement' => $dispute->getAttribute('statement'),
                    'grounds' => $dispute->getAttribute('grounds'),
                    'created_at' => $this->dateString($dispute->getAttribute('created_at')),
                ])
                ->values()
                ->all(),
            'actions' => [
                'can_request_clarification' => true,
                'can_dispute' => true,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function creatorCriteria(Evaluation $evaluation): array
    {
        $results = $evaluation->auditorEvaluations
            ->filter(fn (AuditorEvaluation $auditorEvaluation): bool => $auditorEvaluation->locked_at !== null)
            ->flatMap(fn (AuditorEvaluation $auditorEvaluation) => $auditorEvaluation->criterionResults)
            ->sortBy(fn ($result): int => $result->criterion->sequence ?? PHP_INT_MAX)
            ->groupBy('criterion_id');

        return $results->map(function ($criterionResults): array {
            $result = $criterionResults->sortByDesc('id')->first();
            $criterion = $result?->criterion;

            return [
                'criterion_id' => $criterion?->getKey(),
                'code' => $criterion?->code,
                'name' => $criterion?->name,
                'category' => $criterion?->category,
                'assessment' => $this->enumValue($result?->getAttribute('assessment')),
                'score' => $result?->getAttribute('score'),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function creatorFindings(Evaluation $evaluation): array
    {
        return $evaluation->findings
            ->filter(fn (Finding $finding): bool => $finding->auditor_evaluation_id === null || $finding->auditorEvaluation?->locked_at !== null)
            ->map(fn (Finding $finding): array => $this->creatorFinding($finding))
            ->values()
            ->all();
    }

    /** @param array<int, string> $types */
    private function creatorFindingsByType(Evaluation $evaluation, array $types): array
    {
        return $evaluation->findings
            ->filter(fn (Finding $finding): bool => in_array(strtolower((string) $finding->type), $types, true))
            ->filter(fn (Finding $finding): bool => $finding->auditor_evaluation_id === null || $finding->auditorEvaluation?->locked_at !== null)
            ->map(fn (Finding $finding): array => $this->creatorFinding($finding))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function creatorFinding(Finding $finding): array
    {
        return [
            'id' => $finding->getKey(),
            'type' => (string) $finding->getAttribute('type'),
            'severity' => $finding->getAttribute('severity'),
            'criterion' => $finding->criterion?->code,
            'title' => (string) $finding->getAttribute('title'),
            'description' => (string) $finding->getAttribute('description'),
        ];
    }

    /** @return array<string, mixed> */
    private function reportVersion(ReportVersion $version): array
    {
        return [
            'id' => $version->getKey(),
            'version_number' => $version->version_number,
            'abstract' => $version->abstract,
            'content_structure' => $this->creatorContentStructure($version->content_structure),
            'created_at' => $this->dateString($version->getAttribute('created_at')),
        ];
    }

    /** @return array<string, string> */
    private function creatorContentStructure(mixed $contentStructure): array
    {
        if (is_array($contentStructure)) {
            $summary = $contentStructure['sections']['summary'] ?? null;
            if (is_scalar($summary)) {
                return ['summary' => (string) $summary];
            }
        }

        return [];
    }

    /** @return array<string, mixed>|null */
    private function validation(Evaluation $evaluation): ?array
    {
        $validation = $evaluation->validation;
        if ($validation === null) {
            return null;
        }

        $badge = $validation->badge;

        return [
            'id' => $validation->getKey(),
            'status' => $this->enumValue($validation->getAttribute('status')),
            'issued_at' => $this->dateString($validation->getAttribute('issued_at')),
            'status_reason' => $validation->status_reason,
            'verification_identifier' => $validation->verification_identifier,
            'badge' => $badge === null ? null : [
                'status' => $this->enumValue($badge->getAttribute('status')),
                'embed_version' => $badge->embed_version,
                'verification_identifier' => $badge->verification_identifier,
            ],
            'verification_url' => $validation->publicVerificationRecord === null
                ? null
                : route('public.verify.show', [
                    'verificationIdentifier' => $validation->verification_identifier,
                ]),
        ];
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        return (string) $value;
    }

    private function canAccess(User $user, int $organizationId): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $membership = $user->organizations()
            ->whereKey($organizationId)
            ->first();

        if ($membership === null) {
            return false;
        }

        return in_array(
            (string) $membership->pivot->getAttribute('role'),
            [
                OrganizationRole::Owner->value,
                OrganizationRole::Admin->value,
                OrganizationRole::Editor->value,
            ],
            true,
        );
    }
}
