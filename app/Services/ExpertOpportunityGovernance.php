<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpertOpportunityStatus;
use App\Models\ExpertOpportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ExpertOpportunityGovernance
{
    public function publish(ExpertOpportunity $opportunity, User $actor): ExpertOpportunity
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($opportunity, $actor): ExpertOpportunity {
            $opportunity = ExpertOpportunity::query()->lockForUpdate()->findOrFail($opportunity->getKey());
            if ($opportunity->status !== ExpertOpportunityStatus::Draft) {
                throw new DomainStateTransitionException('Only draft opportunities can be published.');
            }
            if ($opportunity->application_deadline !== null && $opportunity->application_deadline->isPast()) {
                throw new DomainStateTransitionException('An opportunity with an expired application deadline cannot be published.');
            }
            if ($opportunity->starts_at !== null && $opportunity->ends_at !== null && $opportunity->ends_at->lessThanOrEqualTo($opportunity->starts_at)) {
                throw new DomainStateTransitionException('An opportunity must end after it starts.');
            }
            if ($opportunity->expertise_areas === [] && $opportunity->product_types === [] && $opportunity->eligibility_constraints === []) {
                throw new DomainStateTransitionException('An opportunity must define at least one eligibility requirement.');
            }
            $opportunity->forceFill(['status' => ExpertOpportunityStatus::Published, 'published_by' => $actor->getKey(), 'published_at' => now()])->save();
            AuditLogger::record(event: 'expert_opportunity.published', auditable: $opportunity, after: ['status' => ExpertOpportunityStatus::Published->value, 'published_by' => $actor->getKey()], actor: $actor);
            app(ExpertOpportunityNotificationService::class)->published($opportunity);
            return $opportunity->refresh();
        });
    }

    public function close(ExpertOpportunity $opportunity, User $actor): ExpertOpportunity
    {
        return $this->transition($opportunity, $actor, ExpertOpportunityStatus::Closed, [ExpertOpportunityStatus::Published], 'expert_opportunity.closed', 'closed_at');
    }

    public function cancel(ExpertOpportunity $opportunity, User $actor): ExpertOpportunity
    {
        return $this->transition($opportunity, $actor, ExpertOpportunityStatus::Cancelled, [ExpertOpportunityStatus::Draft, ExpertOpportunityStatus::Published, ExpertOpportunityStatus::Closed], 'expert_opportunity.cancelled', 'cancelled_at');
    }

    public function complete(ExpertOpportunity $opportunity, User $actor): ExpertOpportunity
    {
        return $this->transition($opportunity, $actor, ExpertOpportunityStatus::Completed, [ExpertOpportunityStatus::Closed], 'expert_opportunity.completed', 'completed_at');
    }

    /** @param list<ExpertOpportunityStatus> $allowedFrom */
    private function transition(ExpertOpportunity $opportunity, User $actor, ExpertOpportunityStatus $to, array $allowedFrom, string $event, string $timestamp): ExpertOpportunity
    {
        $this->authorize($actor);
        return DB::transaction(function () use ($opportunity, $actor, $to, $allowedFrom, $event, $timestamp): ExpertOpportunity {
            $opportunity = ExpertOpportunity::query()->lockForUpdate()->findOrFail($opportunity->getKey());
            $from = $opportunity->status;
            if (in_array($from, $allowedFrom, true) === false) throw new DomainStateTransitionException("Invalid opportunity transition from {$from->value} to {$to->value}.");
            $opportunity->forceFill(['status' => $to, $timestamp => now()])->save();
            AuditLogger::record(event: $event, auditable: $opportunity, before: ['status' => $from->value], after: ['status' => $to->value], actor: $actor);
            return $opportunity->refresh();
        });
    }

    private function authorize(User $actor): void
    {
        if ($actor->isPlatformAdmin() === false) throw new DomainStateTransitionException('Only platform administrators can govern expert opportunities.');
    }
}
