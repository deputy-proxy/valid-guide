<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\CommunityContributionStatus;
use App\Enums\CommunityReportReason;
use App\Enums\CommunityReportStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Models\AuditorProfile;
use App\Models\CommunityContribution;
use App\Models\CommunityReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CommunityContributionGovernance
{
    public function createDraft(User $expert, string $title, string $body): CommunityContribution
    {
        $profile = $this->eligibleExpert($expert);
        $this->content($title, $body);

        $contribution = CommunityContribution::query()->create([
            'auditor_profile_id' => $profile->getKey(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(8)),
            'title' => trim($title),
            'body' => trim($body),
            'status' => CommunityContributionStatus::Draft,
        ]);

        AuditLogger::record(event: 'community_contribution.created', auditable: $contribution, actor: $expert);

        return $contribution;
    }

    public function updateDraft(CommunityContribution $contribution, User $expert, string $title, string $body): CommunityContribution
    {
        $this->assertOwner($contribution, $expert);
        if (! in_array($contribution->status, [CommunityContributionStatus::Draft, CommunityContributionStatus::Rejected], true)) {
            throw new DomainStateTransitionException('Only draft or rejected contributions can be edited.');
        }
        $this->content($title, $body);

        $contribution->forceFill([
            'title' => trim($title),
            'body' => trim($body),
            'slug' => Str::slug($title).'-'.$contribution->getKey(),
            'status' => CommunityContributionStatus::Draft,
            'moderation_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ])->save();

        AuditLogger::record(event: 'community_contribution.updated', auditable: $contribution, actor: $expert);

        return $contribution->refresh();
    }

    public function submit(CommunityContribution $contribution, User $expert): CommunityContribution
    {
        $this->assertOwner($contribution, $expert);
        if (! in_array($contribution->status, [CommunityContributionStatus::Draft, CommunityContributionStatus::Rejected], true)) {
            throw new DomainStateTransitionException('Only draft or rejected contributions can be submitted.');
        }
        if (trim($contribution->title) === '' || trim($contribution->body) === '') {
            throw new DomainStateTransitionException('A title and body are required before submission.');
        }

        $contribution->forceFill(['status' => CommunityContributionStatus::PendingReview])->save();
        AuditLogger::record(event: 'community_contribution.submitted', auditable: $contribution, actor: $expert);

        return $contribution->refresh();
    }

    public function publish(CommunityContribution $contribution, User $actor): CommunityContribution
    {
        $this->admin($actor);
        if ($contribution->status !== CommunityContributionStatus::PendingReview) {
            throw new DomainStateTransitionException('Only pending contributions can be published.');
        }

        return DB::transaction(function () use ($contribution, $actor): CommunityContribution {
            $contribution = CommunityContribution::query()->lockForUpdate()->findOrFail($contribution->getKey());
            $contribution->forceFill([
                'status' => CommunityContributionStatus::Published,
                'moderation_reason' => null,
                'moderated_by' => $actor->getKey(),
                'moderated_at' => now(),
                'published_at' => now(),
            ])->save();
            AuditLogger::record(event: 'community_contribution.published', auditable: $contribution, actor: $actor);

            return $contribution->refresh();
        });
    }

    public function reject(CommunityContribution $contribution, User $actor, string $reason): CommunityContribution
    {
        $this->admin($actor);
        $this->reason($reason);
        if ($contribution->status !== CommunityContributionStatus::PendingReview) {
            throw new DomainStateTransitionException('Only pending contributions can be rejected.');
        }

        $contribution->forceFill([
            'status' => CommunityContributionStatus::Rejected,
            'moderation_reason' => trim($reason),
            'moderated_by' => $actor->getKey(),
            'moderated_at' => now(),
        ])->save();
        AuditLogger::record(event: 'community_contribution.rejected', auditable: $contribution, actor: $actor);

        return $contribution->refresh();
    }

    public function hide(CommunityContribution $contribution, User $actor, string $reason): CommunityContribution
    {
        return $this->moderateVisibility($contribution, $actor, $reason, CommunityContributionStatus::Hidden, 'community_contribution.hidden', 'hidden_at');
    }

    public function remove(CommunityContribution $contribution, User $actor, string $reason): CommunityContribution
    {
        return $this->moderateVisibility($contribution, $actor, $reason, CommunityContributionStatus::Removed, 'community_contribution.removed', 'removed_at');
    }

    public function report(CommunityContribution $contribution, User $reporter, CommunityReportReason $reason, ?string $details = null): CommunityReport
    {
        if ($contribution->status !== CommunityContributionStatus::Published) {
            throw new DomainStateTransitionException('Only published contributions can be reported.');
        }
        if ($contribution->auditorProfile?->auditor_id === $reporter->getKey()) {
            throw new DomainStateTransitionException('Authors cannot report their own contributions.');
        }
        if ($contribution->reports()->where('reporter_id', $reporter->getKey())->where('status', CommunityReportStatus::Open->value)->exists()) {
            throw new DomainStateTransitionException('You already have an open report for this contribution.');
        }

        $report = CommunityReport::query()->create([
            'community_contribution_id' => $contribution->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reason' => $reason,
            'details' => $details !== null ? trim($details) : null,
            'status' => CommunityReportStatus::Open,
        ]);
        AuditLogger::record(event: 'community_report.created', auditable: $report, actor: $reporter);

        return $report;
    }

    public function resolveReport(CommunityReport $report, User $actor, string $note, bool $dismiss = false): CommunityReport
    {
        $this->admin($actor);
        $this->reason($note);
        if ($report->status !== CommunityReportStatus::Open) {
            throw new DomainStateTransitionException('Only open reports can be resolved.');
        }

        $status = $dismiss ? CommunityReportStatus::Dismissed : CommunityReportStatus::Resolved;
        $report->forceFill([
            'status' => $status,
            'resolution_note' => trim($note),
            'resolved_by' => $actor->getKey(),
            'resolved_at' => now(),
        ])->save();
        AuditLogger::record(event: $dismiss ? 'community_report.dismissed' : 'community_report.resolved', auditable: $report, actor: $actor);

        return $report->refresh();
    }

    private function moderateVisibility(CommunityContribution $contribution, User $actor, string $reason, CommunityContributionStatus $status, string $event, string $timestamp): CommunityContribution
    {
        $this->admin($actor);
        $this->reason($reason);
        if (! in_array($contribution->status, [CommunityContributionStatus::Published, CommunityContributionStatus::Hidden], true)) {
            throw new DomainStateTransitionException('Only published or hidden contributions can be moderated.');
        }

        $contribution->forceFill([
            'status' => $status,
            'moderation_reason' => trim($reason),
            'moderated_by' => $actor->getKey(),
            'moderated_at' => now(),
            $timestamp => now(),
        ])->save();
        AuditLogger::record(event: $event, auditable: $contribution, actor: $actor);

        return $contribution->refresh();
    }

    private function eligibleExpert(User $expert): AuditorProfile
    {
        $profile = $expert->auditorProfile;
        if ($profile === null || $profile->status !== AuditorProfileStatus::Approved || $profile->expertBoardMembership?->status !== ExpertBoardMembershipStatus::Approved) {
            throw new DomainStateTransitionException('An approved Expert Board member is required.');
        }
        if (! app(AuditorEligibility::class)->hasCurrentAnnualClearance($expert)) {
            throw new DomainStateTransitionException('A current annual conflict clearance is required.');
        }

        return $profile;
    }

    private function assertOwner(CommunityContribution $contribution, User $expert): void
    {
        if ($contribution->auditorProfile?->auditor_id !== $expert->getKey()) {
            throw new DomainStateTransitionException('You may only manage your own contributions.');
        }
    }

    private function admin(User $actor): void
    {
        if (! $actor->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can moderate community content.');
        }
    }

    private function content(string $title, string $body): void
    {
        if (trim($title) === '' || trim($body) === '') {
            throw new DomainStateTransitionException('A title and body are required.');
        }
        if (mb_strlen(trim($title)) > 255) {
            throw new DomainStateTransitionException('The title may not exceed 255 characters.');
        }
    }

    private function reason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new DomainStateTransitionException('A moderation reason is required.');
        }
    }
}
