<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Evaluation;
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
                'productRelease.standardVersion.standard',
                'report.currentVersion',
                'report.versions',
                'validation.badge',
                'validation.publicVerificationRecord',
                'clarificationRequests',
                'disputes',
            ])
            ->findOrFail($evaluationId);

        $organization = $evaluation->request?->organization;
        if ($organization === null || ! $this->canAccess($user, $organization->id)) {
            throw new AuthorizationException('You are not authorized to access this creator report.');
        }

        $report = $evaluation->report;
        if ($report === null || $report->creator_visible_at === null) {
            throw new AuthorizationException('This report is not available to creators.');
        }

        $currentVersion = $report->currentVersion;

        return [
            'evaluation' => [
                'id' => $evaluation->getKey(),
                'status' => (string) $evaluation->getAttribute('status'),
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
                'name' => $evaluation->productRelease->product->name,
            ],
            'release' => $evaluation->productRelease === null ? null : [
                'id' => $evaluation->productRelease->getKey(),
                'version' => $evaluation->productRelease->version,
            ],
            'standard' => $evaluation->productRelease?->standardVersion === null ? null : [
                'id' => $evaluation->productRelease->standardVersion->getKey(),
                'name' => $evaluation->productRelease->standardVersion->standard?->name,
                'version' => $evaluation->productRelease->standardVersion->version,
            ],
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
            'findings' => $currentVersion === null ? [] : $this->creatorFindings($currentVersion),
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

    /** @return array<string, mixed> */
    private function reportVersion(ReportVersion $version): array
    {
        return [
            'id' => $version->getKey(),
            'version_number' => $version->version_number,
            'abstract' => $version->abstract,
            'content_structure' => $version->content_structure,
            'created_at' => $this->dateString($version->getAttribute('created_at')),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function creatorFindings(ReportVersion $version): array
    {
        $content = $version->getAttribute('content_structure');
        if (! is_array($content)) {
            return [];
        }

        $findings = $content['findings'] ?? [];
        if (! is_array($findings)) {
            return [];
        }

        return array_values(array_filter(
            $findings,
            static fn (mixed $finding): bool => is_array($finding),
        ));
    }

    /** @return array<string, mixed>|null */
    private function validation(Evaluation $evaluation): ?array
    {
        $validation = $evaluation->validation;
        if ($validation === null) {
            return null;
        }

        $badge = $validation->badge;
        $status = $validation->getAttribute('status');
        $badgeStatus = $badge?->getAttribute('status');

        return [
            'id' => $validation->getKey(),
            'status' => is_object($status) && property_exists($status, 'value') ? $status->value : (string) $status,
            'issued_at' => $this->dateString($validation->getAttribute('issued_at')),
            'status_reason' => $validation->status_reason,
            'verification_identifier' => $validation->verification_identifier,
            'badge' => $badge === null ? null : [
                'status' => is_object($badgeStatus) && property_exists($badgeStatus, 'value') ? $badgeStatus->value : (string) $badgeStatus,
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
        if ($user->platform_role === 'admin') {
            return true;
        }

        $membership = $user->organizations()
            ->whereKey($organizationId)
            ->first();

        if ($membership === null) {
            return false;
        }

        return in_array(
            (string) $membership->pivot->role,
            [
                OrganizationRole::Owner->value,
                OrganizationRole::Admin->value,
                OrganizationRole::Editor->value,
            ],
            true,
        );
    }
}
