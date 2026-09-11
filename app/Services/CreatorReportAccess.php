<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\ClarificationRequest;
use App\Models\Dispute;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CreatorReportAccess
{
    /** @return array<string, mixed> */
    public function show(User $actor, int|string $evaluationId): array
    {
        $evaluation = Evaluation::query()
            ->with([
                'request.organization',
                'product',
                'productRelease',
                'standardVersion.standard',
                'report.currentVersion',
                'report.versions',
                'validation.badge',
                'validation.publicVerificationRecord',
                'clarificationRequests',
                'disputes',
            ])
            ->findOrFail($evaluationId);

        $request = $evaluation->request;
        if ($request === null || $request->organization === null) {
            throw new AuthorizationException('The evaluation does not have an accessible creator organization.');
        }

        $organization = $request->organization;
        $this->authorize($actor, $organization);

        $product = $evaluation->product;
        $productRelease = $evaluation->productRelease;
        $standardVersion = $evaluation->standardVersion;
        if ($product === null || $productRelease === null || $standardVersion === null || $standardVersion->standard === null) {
            throw new AuthorizationException('The evaluation is missing required historical context.');
        }

        $report = $evaluation->report;
        if ($report === null || $report->creator_visible_at === null) {
            throw new AuthorizationException('The evaluation report is not available to this organization.');
        }

        $currentVersion = $report->currentVersion;

        return [
            'evaluation' => [
                'id' => $evaluation->getKey(),
                'status' => $evaluation->status->value,
                'decision' => $evaluation->decision,
                'overall_score' => $evaluation->overall_score,
                'completed_at' => $evaluation->completed_at?->toISOString(),
            ],
            'organization' => [
                'id' => $organization->getKey(),
                'name' => (string) $organization->name,
            ],
            'product' => [
                'id' => $product->getKey(),
                'title' => (string) $product->title,
                'slug' => (string) $product->slug,
            ],
            'release' => [
                'id' => $productRelease->getKey(),
                'identifier' => (string) $productRelease->release_identifier,
                'version' => $productRelease->version !== null
                    ? (string) $productRelease->version
                    : null,
                'edition' => $productRelease->edition,
                'published_at' => $productRelease->published_at?->toISOString(),
            ],
            'standard_version' => [
                'id' => $standardVersion->getKey(),
                'version' => (string) $standardVersion->version,
                'name' => (string) $standardVersion->standard->name,
            ],
            'report' => [
                'id' => $report->getKey(),
                'current_version_id' => $currentVersion?->getKey(),
                'current' => $currentVersion === null ? null : $this->version($currentVersion),
                'versions' => $report->versions
                    ->sortByDesc('version_number')
                    ->values()
                    ->map(fn (ReportVersion $version): array => $this->version($version))
                    ->all(),
            ],
            'findings' => $this->creatorFindings($currentVersion),
            'validation' => $this->validation($evaluation),
            'clarifications' => $evaluation->clarificationRequests
                ->map(fn (ClarificationRequest $clarification): array => [
                    'id' => $clarification->getKey(),
                    'type' => $clarification->type->value,
                    'status' => $clarification->status->value,
                    'message' => (string) $clarification->message,
                    'response' => $clarification->response,
                    'submitted_at' => $clarification->submitted_at?->toISOString(),
                    'resolved_at' => $clarification->resolved_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'disputes' => $evaluation->disputes
                ->map(fn (Dispute $dispute): array => [
                    'id' => $dispute->getKey(),
                    'status' => $dispute->status->value,
                    'grounds' => $dispute->grounds,
                    'statement' => $dispute->decision_rationale,
                    'submitted_at' => $dispute->submitted_at?->toISOString(),
                    'resolved_at' => $dispute->resolved_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'actions' => [
                'clarification' => $evaluation->status->value === 'completed',
                'dispute' => $evaluation->status->value === 'completed'
                    && ! $evaluation->disputes->contains(
                        fn (Dispute $dispute): bool => in_array(
                            $dispute->status->value,
                            ['submitted', 'under_review'],
                            true,
                        ),
                    ),
            ],
        ];
    }

    private function authorize(User $actor, Organization $organization): void
    {
        if ($actor->isPlatformAdmin()) {
            return;
        }

        $allowed = $organization->users()
            ->whereKey($actor->getKey())
            ->wherePivotIn('role', [
                OrganizationRole::Owner->value,
                OrganizationRole::Admin->value,
                OrganizationRole::Editor->value,
            ])
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException('The user cannot access creator reports for this organization.');
        }
    }

    /** @return array<string, mixed> */
    private function version(ReportVersion $version): array
    {
        return [
            'id' => $version->getKey(),
            'version_number' => (int) $version->version_number,
            'abstract' => $version->abstract,
            'content_structure' => $version->content_structure,
            'decision_snapshot' => $version->decision_snapshot,
            'standard_version_snapshot' => $version->standard_version_snapshot,
            'published_at' => $version->published_at?->toISOString(),
            'change_reason' => $version->change_reason,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function creatorFindings(?ReportVersion $version): array
    {
        if ($version === null || ! is_array($version->content_structure)) {
            return [];
        }

        $findings = $version->content_structure['findings'] ?? [];
        if (! is_array($findings)) {
            return [];
        }

        $result = [];
        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $title = is_string($finding['title'] ?? null) ? $finding['title'] : '';
            $description = is_string($finding['description'] ?? null) ? $finding['description'] : '';
            if ($title === '' && $description === '') {
                continue;
            }

            $result[] = [
                'type' => is_string($finding['type'] ?? null) ? $finding['type'] : 'finding',
                'severity' => is_string($finding['severity'] ?? null) ? $finding['severity'] : null,
                'criterion' => is_string($finding['criterion'] ?? null) ? $finding['criterion'] : null,
                'title' => $title,
                'description' => $description,
            ];
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function validation(Evaluation $evaluation): ?array
    {
        $validation = $evaluation->validation;
        if ($validation === null) {
            return null;
        }

        return [
            'id' => $validation->getKey(),
            'status' => $validation->status->value,
            'issued_at' => $validation->issued_at?->toISOString(),
            'status_reason' => $validation->status_reason,
            'verification_identifier' => $validation->verification_identifier,
            'badge' => $validation->badge === null ? null : [
                'status' => $validation->badge->status->value,
                'embed_version' => $validation->badge->embed_version,
                'verification_identifier' => $validation->badge->verification_identifier,
            ],
            'verification_url' => $validation->publicVerificationRecord === null
                ? null
                : route('public.verify.show', [
                    'verificationIdentifier' => $validation->verification_identifier,
                ]),
        ];
    }
}
