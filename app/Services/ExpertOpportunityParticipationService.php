<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\ExpertBoardMembershipStatus;
use App\Enums\ExpertOpportunityParticipationStatus;
use App\Enums\ExpertOpportunityStatus;
use App\Models\AuditorProfile;
use App\Models\ExpertOpportunity;
use App\Models\ExpertOpportunityParticipation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ExpertOpportunityParticipationService
{
    public function apply(ExpertOpportunity $opportunity, User $expert, string $disclosure): ExpertOpportunityParticipation
    {
        $profile = $this->eligible($expert, $opportunity);
        if (trim($disclosure) === '') throw new DomainStateTransitionException('A conflict-of-interest disclosure is required.');
        if ($opportunity->status !== ExpertOpportunityStatus::Published || ($opportunity->application_deadline !== null && $opportunity->application_deadline->isPast())) throw new DomainStateTransitionException('This opportunity is not accepting applications.');
        if ($opportunity->participations()->where('auditor_profile_id', $profile->getKey())->exists()) throw new DomainStateTransitionException('The Expert has already applied to this opportunity.');
        return DB::transaction(function () use ($opportunity, $profile, $expert, $disclosure): ExpertOpportunityParticipation {
            $participation = ExpertOpportunityParticipation::query()->create(['expert_opportunity_id' => $opportunity->getKey(), 'auditor_profile_id' => $profile->getKey(), 'status' => ExpertOpportunityParticipationStatus::Applied, 'conflict_disclosure' => trim($disclosure), 'applied_at' => now()]);
            AuditLogger::record(event: 'expert_opportunity.application_submitted', auditable: $participation, actor: $expert);
            app(ExpertOpportunityNotificationService::class)->application($participation);
            return $participation->refresh();
        });
    }

    public function withdraw(ExpertOpportunityParticipation $participation, User $expert): ExpertOpportunityParticipation
    {
        $this->assertOwner($participation, $expert);
        if ($participation->status !== ExpertOpportunityParticipationStatus::Applied) throw new DomainStateTransitionException('Only active applications can be withdrawn.');
        $participation->forceFill(['status' => ExpertOpportunityParticipationStatus::Withdrawn, 'withdrawn_at' => now()])->save();
        AuditLogger::record(event: 'expert_opportunity.application_withdrawn', auditable: $participation, actor: $expert);
        return $participation->refresh();
    }

    public function determineConflict(ExpertOpportunityParticipation $participation, User $actor, string $outcome): ExpertOpportunityParticipation
    {
        $this->admin($actor);
        if ($participation->conflict_determined_at !== null || ! in_array($outcome, ['cleared', 'conflicted'], true)) throw new DomainStateTransitionException('The conflict determination cannot be changed.');
        $participation->forceFill(['conflict_outcome' => $outcome, 'conflict_determined_by' => $actor->getKey(), 'conflict_determined_at' => now()])->save();
        AuditLogger::record(event: 'expert_opportunity.conflict_determined', auditable: $participation, actor: $actor);
        return $participation->refresh();
    }

    public function select(ExpertOpportunityParticipation $participation, User $actor): ExpertOpportunityParticipation
    {
        $this->admin($actor);
        if ($participation->status !== ExpertOpportunityParticipationStatus::Applied || $participation->conflict_outcome !== 'cleared' || $participation->conflict_determined_at === null) throw new DomainStateTransitionException('Only an eligible, conflict-cleared application can be selected.');
        $this->eligible($participation->auditorProfile->auditor, $participation->opportunity);
        if (! in_array($participation->opportunity->status, [ExpertOpportunityStatus::Published, ExpertOpportunityStatus::Closed], true)) throw new DomainStateTransitionException('The opportunity is no longer accepting a selection.');
        $participation->forceFill(['status' => ExpertOpportunityParticipationStatus::Selected, 'selected_at' => now(), 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now()])->save();
        AuditLogger::record(event: 'expert_opportunity.expert_selected', auditable: $participation, actor: $actor);
        app(ExpertOpportunityNotificationService::class)->selected($participation);
        return $participation->refresh();
    }

    public function accept(ExpertOpportunityParticipation $participation, User $expert): ExpertOpportunityParticipation
    {
        $this->assertOwner($participation, $expert);
        if ($participation->status !== ExpertOpportunityParticipationStatus::Selected) throw new DomainStateTransitionException('Only selected experts can accept an opportunity.');
        $this->eligible($expert, $participation->opportunity);
        $participation->forceFill(['status' => ExpertOpportunityParticipationStatus::Accepted, 'accepted_at' => now()])->save();
        AuditLogger::record(event: 'expert_opportunity.engagement_accepted', auditable: $participation, actor: $expert);
        return $participation->refresh();
    }

    public function reject(ExpertOpportunityParticipation $participation, User $actor, string $reason): ExpertOpportunityParticipation
    {
        $this->admin($actor); return $this->review($participation, ExpertOpportunityParticipationStatus::Rejected, $reason, $actor);
    }

    public function cancel(ExpertOpportunityParticipation $participation, User $actor, string $reason): ExpertOpportunityParticipation
    {
        $this->admin($actor); return $this->review($participation, ExpertOpportunityParticipationStatus::Cancelled, $reason, $actor);
    }

    public function complete(ExpertOpportunityParticipation $participation, User $actor): ExpertOpportunityParticipation
    {
        $this->admin($actor);
        if ($participation->status !== ExpertOpportunityParticipationStatus::Accepted) throw new DomainStateTransitionException('Only accepted engagements can be completed.');
        $participation->forceFill(['status' => ExpertOpportunityParticipationStatus::Completed, 'completed_at' => now()])->save();
        AuditLogger::record(event: 'expert_opportunity.engagement_completed', auditable: $participation, actor: $actor);
        return $participation->refresh();
    }

    private function review(ExpertOpportunityParticipation $participation, ExpertOpportunityParticipationStatus $status, string $reason, User $actor): ExpertOpportunityParticipation
    {
        $reason = trim($reason);
        if ($reason === '' || ! in_array($participation->status, [ExpertOpportunityParticipationStatus::Applied, ExpertOpportunityParticipationStatus::Selected, ExpertOpportunityParticipationStatus::Accepted], true)) throw new DomainStateTransitionException('The participation cannot be changed.');
        $participation->forceFill(['status' => $status, 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'decision_reason' => $reason])->save();
        AuditLogger::record(event: 'expert_opportunity.participation_changed', auditable: $participation, actor: $actor);
        return $participation->refresh();
    }

    private function eligible(User $expert, ExpertOpportunity $opportunity): AuditorProfile
    {
        $profile = $expert->auditorProfile;
        if ($profile === null || $profile->status !== AuditorProfileStatus::Approved || $profile->expertBoardMembership?->status !== ExpertBoardMembershipStatus::Approved) throw new DomainStateTransitionException('An approved Expert Board member is required.');
        if (! app(AuditorEligibility::class)->hasCurrentAnnualClearance($expert)) throw new DomainStateTransitionException('A current annual conflict clearance is required.');
        $constraints = $opportunity->eligibility_constraints ?? [];
        if (($constraints['methodology_literate'] ?? false) === true && $profile->methodology_literate !== true) throw new DomainStateTransitionException('The opportunity requires methodology literacy.');
        $requiredTypes = array_values(array_filter($opportunity->product_types ?? [], 'is_string'));
        if ($requiredTypes !== [] && array_intersect($requiredTypes, $profile->format_experience ?? []) === []) throw new DomainStateTransitionException('The Expert does not match the required product type experience.');
        $requiredAreas = array_values(array_filter($opportunity->expertise_areas ?? [], 'is_string'));
        if ($requiredAreas !== []) {
            $topics = $profile->competencies()->whereNotNull('verified_at')->pluck('topic')->filter()->map(fn ($topic): string => strtolower((string) $topic))->all();
            if (array_intersect(array_map('strtolower', $requiredAreas), $topics) === []) throw new DomainStateTransitionException('The Expert does not match the required verified expertise.');
        }
        return $profile;
    }

    private function assertOwner(ExpertOpportunityParticipation $participation, User $expert): void
    {
        if ((int) $participation->auditorProfile->auditor_id !== (int) $expert->getKey()) throw new DomainStateTransitionException('You may only manage your own participation.');
    }

    private function admin(User $actor): void
    {
        if (! $actor->isPlatformAdmin()) throw new DomainStateTransitionException('Only platform administrators can govern opportunity participation.');
    }
}
