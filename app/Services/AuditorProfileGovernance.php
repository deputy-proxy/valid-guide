<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Models\AuditorCompetency;
use App\Models\AuditorProfile;
use App\Models\AuditorProfileReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AuditorProfileGovernance
{
    public function approve(AuditorProfile $profile, User $actor): AuditorProfile
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($profile, $actor): AuditorProfile {
            $profile = AuditorProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();
            /** @var AuditorProfile $profile */
            $status = $this->status($profile);

            if (! in_array($status, [AuditorProfileStatus::Pending, AuditorProfileStatus::Rejected, AuditorProfileStatus::Suspended], true)) {
                throw new DomainStateTransitionException('Only pending, rejected, or suspended Auditor profiles can be approved.');
            }
            $profile->forceFill([
                'status' => AuditorProfileStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            AuditorProfileReview::query()->create([
                'auditor_profile_id' => $profile->id,
                'reviewed_by' => $actor->id,
                'action' => AuditorProfileStatus::Approved->value,
                'created_at' => now(),
            ]);

            AuditLogger::record(
                event: 'auditor_profile.approved',
                auditable: $profile,
                after: ['status' => AuditorProfileStatus::Approved->value, 'approved_by' => $actor->id],
            );

            return $profile->refresh();
        });
    }

    public function reject(AuditorProfile $profile, User $actor, string $reason): AuditorProfile
    {
        $this->authorize($actor);
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainStateTransitionException('An Auditor profile rejection requires a reason.');
        }

        return $this->changeStatus($profile, $actor, AuditorProfileStatus::Rejected, $reason, [AuditorProfileStatus::Pending, AuditorProfileStatus::Suspended]);
    }

    public function suspend(AuditorProfile $profile, User $actor, string $reason): AuditorProfile
    {
        $this->authorize($actor);
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainStateTransitionException('Suspending an Auditor profile requires a reason.');
        }

        return $this->changeStatus($profile, $actor, AuditorProfileStatus::Suspended, $reason, [AuditorProfileStatus::Approved]);
    }

    public function verifyCompetency(AuditorCompetency $competency, User $actor): AuditorCompetency
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($competency, $actor): AuditorCompetency {
            $competency = AuditorCompetency::query()
                ->with('profile')
                ->lockForUpdate()
                ->findOrFail($competency->getKey());
            /** @var AuditorCompetency $competency */
            if ($competency->verified_at !== null) {
                throw new DomainStateTransitionException('An Auditor competency that has been verified cannot be verified again.');
            }
            if (blank($competency->topic) || blank($competency->experience_type)) {
                throw new DomainStateTransitionException('An Auditor competency requires a topic and experience type before verification.');
            }
            $competency->forceFill([
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ])->save();

            AuditLogger::record(
                event: 'auditor_competency.verified',
                auditable: $competency,
                after: [
                    'auditor_profile_id' => $competency->auditor_profile_id,
                    'topic' => $competency->topic,
                    'verified_by' => $actor->id,
                ],
            );

            return $competency->refresh();
        });
    }

    /** @param list<AuditorProfileStatus> $allowedFrom */
    /** @param list<AuditorProfileStatus> $allowedFrom */
    private function changeStatus(
        AuditorProfile $profile,
        User $actor,
        AuditorProfileStatus $to,
        string $reason,
        array $allowedFrom,
    ): AuditorProfile {
        return DB::transaction(function () use ($profile, $actor, $to, $reason, $allowedFrom): AuditorProfile {
            $profile = AuditorProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();
            /** @var AuditorProfile $profile */
            $from = $this->status($profile);

            if (! in_array($from, $allowedFrom, true)) {
                throw new DomainStateTransitionException("Invalid Auditor profile transition from {$from->value} to {$to->value}.");
            }
            $profile->forceFill(['status' => $to])->save();

            AuditorProfileReview::query()->create([
                'auditor_profile_id' => $profile->id,
                'reviewed_by' => $actor->id,
                'action' => $to->value,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            AuditLogger::record(
                event: 'auditor_profile.status_changed',
                auditable: $profile,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
                metadata: ['reason' => $reason],
            );

            return $profile->refresh();
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can govern Auditor profiles.');
        }
    }

    private function status(AuditorProfile $profile): AuditorProfileStatus
    {
        return $profile->status;
    }
}
