<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

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
                'standardVersion',
                'report.currentVersion',
                'report.versions',
                'validation.badge',
                'validation.publicVerificationRecord',
                'findings.criterion',
                'clarificationRequests',
                'disputes',
            ])
            ->findOrFail($evaluationId);

        $organization = $evaluation->request->organization;
        $this->authorize($actor, $organization);

        if ($evaluation->report === null || $evaluation->report->creator_visible_at === null) {
            throw new AuthorizationException('The evaluation report is not available to this organization.');
        }

        $report = $evaluation->report;
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
                'id' => $evaluation->product->getKey(),
                'title' => (string) $evaluation->product->title,
                'slug' => (string) $evaluation->product->slug,
            ],
            'release' => [
                'id' => $evaluation->productRelease->getKey(),
                'identifier' => (string) $evaluation->productRelease->release_identifier,
                'version' => $evaluation->productRelease->version !== null ? (string) $evaluation->productRelease->version : null,
                'edition' => $evaluation->productRelease->edition,
                'published_at' => $evaluation->productRelease->published_at?->toISOString(),
            ],
            'standard_version' => [
                'id' => $evaluation->standardVersion->getKey(),
                'version' => (string) $evaluation->standardVersion->version,
                'name' => (string) $evaluation->standardVersion->evaluationStandard->name,
            ],
            'report' => [
                'id' => $report->getKey(),
                'current_version_id' => $currentVersion?->getKey(),
                'current' => $currentVersion === null ? null : $this->version($currentVersion),
                'versions' => $report->versions
                    ->sortByDesc('version_number')
                    ->values()
                    ->map(fn ($version): array => $this->version($version))
                    ->all(),
            ],
            'findings' => $evaluation->findings
                ->filter(fn ($finding): bool => $finding->status !== 'private')
                ->map(fn ($finding): array => [
                    'id' => $finding->getKey(),
                    'criterion' => $finding->criterion?->name,
                    'type' => (string) $finding->type,
                    'severity' => $finding->severity,
                    'title' => (string) $finding->title,
                    'description' => (string) $finding->description,
                ])
                ->values()
                ->all(),
            'validation' => $this->validation($evaluation),
            'clarifications' => $evaluation->clarificationRequests->map(fn ($request): array => [
                'id' => $request->getKey(),
                'type' => $request->type->value,
                'status' => $request->status->value,
                'message' => (string) $request->message,
                'response' => $request->response,
                'submitted_at' => $request->submitted_at?->toISOString(),
                'resolved_at' => $request->resolved_at?->toISOString(),
            ])->values()->all(),
            'disputes' => $evaluation->disputes->map(fn ($dispute): array => [
                'id' => $dispute->getKey(),
                'status' => $dispute->status->value,
                'grounds' => $dispute->grounds,
                'statement' => $dispute->decision_rationale,
                'submitted_at' => $dispute->submitted_at?->toISOString(),
                'resolved_at' => $dispute->resolved_at?->toISOString(),
            ])->values()->all(),
            'actions' => [
                'clarification' => $evaluation->status->value === 'completed',
                'dispute' => $evaluation->status->value === 'completed'
                    && ! $evaluation->disputes->contains(fn ($dispute): bool => in_array($dispute->status->value, ['submitted', 'under_review'], true)),
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
    private function version(mixed $version): array
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
                : route('public.verify.show', ['verificationIdentifier' => $validation->verification_identifier]),
        ];
    }
}
