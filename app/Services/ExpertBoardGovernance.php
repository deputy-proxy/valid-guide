<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Models\ExpertBoardMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ExpertBoardGovernance
{
    public function apply(User $auditor): ExpertBoardMembership
    {
        $profile = $auditor->auditorProfile;

        if ($profile === null) {
            throw new DomainStateTransitionException('An Auditor profile is required before applying to the Expert Board.');
        }

        if ($profile->status !== AuditorProfileStatus::Approved) {
            throw new DomainStateTransitionException('Only approved Auditors can apply to the Expert Board.');
        }

        return DB::transaction(function () use ($profile, $auditor): ExpertBoardMembership {
            $membership = ExpertBoardMembership::query()
                ->where('auditor_profile_id', $profile->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership !== null && in_array($membership->status, [
                ExpertBoardMembershipStatus::Rejected,
                ExpertBoardMembershipStatus::Removed,
            ], true) === false) {
                throw new DomainStateTransitionException('The Auditor already has an active Expert Board application or membership.');
            }

            if ($membership === null) {
                $membership = ExpertBoardMembership::query()->create([
                    'auditor_profile_id' => $profile->getKey(),
                    'status' => ExpertBoardMembershipStatus::Pending,
                    'applied_at' => now(),
                ]);
            } else {
                $membership->forceFill([
                    'status' => ExpertBoardMembershipStatus::Pending,
                    'applied_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'decision_reason' => null,
                ])->save();
            }

            AuditLogger::record(
                event: 'expert_board.application_submitted',
                auditable: $membership,
                after: [
                    'auditor_profile_id' => $profile->getKey(),
                    'status' => ExpertBoardMembershipStatus::Pending->value,
                ],
                actor: $auditor,
            );

            return $membership->refresh();
        });
    }

    public function approve(ExpertBoardMembership $membership, User $actor): ExpertBoardMembership
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($membership, $actor): ExpertBoardMembership {
            $membership = ExpertBoardMembership::query()
                ->with('auditorProfile.auditor')
                ->lockForUpdate()
                ->findOrFail($membership->getKey());
            /** @var ExpertBoardMembership $membership */
            if ($membership->status !== ExpertBoardMembershipStatus::Pending) {
                throw new DomainStateTransitionException('Only pending Expert Board applications can be approved.');
            }

            $profile = $membership->auditorProfile;
            if ($profile->status !== AuditorProfileStatus::Approved) {
                throw new DomainStateTransitionException('Only Auditors with an approved Auditor profile can join the Expert Board.');
            }

            if (app(AuditorEligibility::class)->hasCurrentAnnualClearance($profile->auditor) === false) {
                throw new DomainStateTransitionException('The Auditor must have a current annual conflict declaration cleared before joining the Expert Board.');
            }

            $membership->forceFill([
                'status' => ExpertBoardMembershipStatus::Approved,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'suspended_at' => null,
                'removed_at' => null,
                'decision_reason' => null,
            ])->save();

            AuditLogger::record(
                event: 'expert_board.membership_approved',
                auditable: $membership,
                after: [
                    'status' => ExpertBoardMembershipStatus::Approved->value,
                    'reviewed_by' => $actor->getKey(),
                ],
                actor: $actor,
            );

            return $membership->refresh();
        });
    }

    public function reject(ExpertBoardMembership $membership, User $actor, string $reason): ExpertBoardMembership
    {
        return $this->changeStatus(
            $membership,
            $actor,
            ExpertBoardMembershipStatus::Rejected,
            $reason,
            [ExpertBoardMembershipStatus::Pending],
            'expert_board.application_rejected',
        );
    }

    public function suspend(ExpertBoardMembership $membership, User $actor, string $reason): ExpertBoardMembership
    {
        return $this->changeStatus(
            $membership,
            $actor,
            ExpertBoardMembershipStatus::Suspended,
            $reason,
            [ExpertBoardMembershipStatus::Approved],
            'expert_board.membership_suspended',
        );
    }

    public function reinstate(ExpertBoardMembership $membership, User $actor): ExpertBoardMembership
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($membership, $actor): ExpertBoardMembership {
            $membership = ExpertBoardMembership::query()
                ->with('auditorProfile.auditor')
                ->lockForUpdate()
                ->findOrFail($membership->getKey());
            /** @var ExpertBoardMembership $membership */
            if ($membership->status !== ExpertBoardMembershipStatus::Suspended) {
                throw new DomainStateTransitionException('Only suspended Expert Board memberships can be reinstated.');
            }

            $profile = $membership->auditorProfile;
            if ($profile->status !== AuditorProfileStatus::Approved) {
                throw new DomainStateTransitionException('The Auditor must have an approved Auditor profile before reinstatement.');
            }

            if (app(AuditorEligibility::class)->hasCurrentAnnualClearance($profile->auditor) === false) {
                throw new DomainStateTransitionException('The Auditor must have a current annual conflict declaration cleared before reinstatement.');
            }

            $membership->forceFill([
                'status' => ExpertBoardMembershipStatus::Approved,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'suspended_at' => null,
                'decision_reason' => null,
            ])->save();

            AuditLogger::record(
                event: 'expert_board.membership_reinstated',
                auditable: $membership,
                before: ['status' => ExpertBoardMembershipStatus::Suspended->value],
                after: [
                    'status' => ExpertBoardMembershipStatus::Approved->value,
                    'reviewed_by' => $actor->getKey(),
                ],
                actor: $actor,
            );

            return $membership->refresh();
        });
    }

    public function remove(ExpertBoardMembership $membership, User $actor, string $reason): ExpertBoardMembership
    {
        return $this->changeStatus(
            $membership,
            $actor,
            ExpertBoardMembershipStatus::Removed,
            $reason,
            [ExpertBoardMembershipStatus::Approved, ExpertBoardMembershipStatus::Suspended],
            'expert_board.membership_removed',
        );
    }

    /** @param list<ExpertBoardMembershipStatus> $allowedFrom */
    private function changeStatus(
        ExpertBoardMembership $membership,
        User $actor,
        ExpertBoardMembershipStatus $to,
        string $reason,
        array $allowedFrom,
        string $event,
    ): ExpertBoardMembership {
        $this->authorize($actor);
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainStateTransitionException('An Expert Board status change requires a reason.');
        }

        return DB::transaction(function () use ($membership, $actor, $to, $reason, $allowedFrom, $event): ExpertBoardMembership {
            $membership = ExpertBoardMembership::query()->lockForUpdate()->findOrFail($membership->getKey());
            /** @var ExpertBoardMembership $membership */
            $from = $membership->status;

            if (in_array($from, $allowedFrom, true) === false) {
                throw new DomainStateTransitionException("Invalid Expert Board transition from {$from->value} to {$to->value}.");
            }

            $membership->forceFill([
                'status' => $to,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'decision_reason' => $reason,
                'suspended_at' => $to === ExpertBoardMembershipStatus::Suspended ? now() : null,
                'removed_at' => $to === ExpertBoardMembershipStatus::Removed ? now() : null,
            ])->save();

            AuditLogger::record(
                event: $event,
                auditable: $membership,
                before: ['status' => $from->value],
                after: [
                    'status' => $to->value,
                    'reviewed_by' => $actor->getKey(),
                ],
                metadata: ['reason' => $reason],
                actor: $actor,
            );

            return $membership->refresh();
        });
    }

    private function authorize(User $actor): void
    {
        if ($actor->isPlatformAdmin() === false) {
            throw new DomainStateTransitionException('Only platform administrators can govern the Expert Board.');
        }
    }
}
