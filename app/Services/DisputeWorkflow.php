<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DisputeGround;
use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use App\Enums\EvaluationStatus;
use App\Models\Dispute;
use App\Models\DisputeReviewer;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisputeWorkflow
{
    public function submit(Evaluation $evaluation, Organization $organization, User $submittedBy, array $grounds, string $statement): Dispute
    {
        if (! $this->creatorCanAct($organization, $submittedBy)) {
            throw new DomainStateTransitionException('The user cannot submit disputes for this organization.');
        }
        if ($evaluation->request->organization_id !== $organization->id) {
            throw new DomainStateTransitionException('The dispute organization does not own the evaluation.');
        }
        if ($evaluation->status !== EvaluationStatus::Completed) {
            throw new DomainStateTransitionException('A formal dispute can only be submitted after an evaluation is completed.');
        }
        if (trim($statement) === '') {
            throw new DomainStateTransitionException('A formal dispute requires a factual statement of the alleged error.');
        }

        $normalizedGrounds = collect($grounds)
            ->map(fn (DisputeGround|string $ground): string => $ground instanceof DisputeGround ? $ground->value : $ground)
            ->unique()->values()->all();

        if ($normalizedGrounds === [] || collect($normalizedGrounds)->contains(fn (string $ground): bool => DisputeGround::tryFrom($ground) === null)) {
            throw new DomainStateTransitionException('A formal dispute must use only the approved dispute grounds.');
        }
        if ($evaluation->disputes()->whereIn('status', [DisputeStatus::Submitted->value, DisputeStatus::UnderReview->value])->exists()) {
            throw new DomainStateTransitionException('The evaluation already has an active formal dispute.');
        }

        return DB::transaction(function () use ($evaluation, $organization, $submittedBy, $normalizedGrounds, $statement): Dispute {
            $dispute = Dispute::query()->create([
                'evaluation_id' => $evaluation->id,
                'organization_id' => $organization->id,
                'submitted_by' => $submittedBy->id,
                'type' => 'formal',
                'grounds' => $normalizedGrounds,
                'statement' => trim($statement),
                'status' => DisputeStatus::Submitted,
                'submitted_at' => now(),
            ]);
            AuditLogger::record(event: 'dispute.submitted', auditable: $dispute, after: [
                'evaluation_id' => $evaluation->id,
                'submitted_by' => $submittedBy->id,
                'grounds' => $normalizedGrounds,
            ]);
            return $dispute->refresh();
        });
    }

    public function assignReviewer(Dispute $dispute, User $reviewer, User $assignedBy): DisputeReviewer
    {
        $this->requirePlatformAdmin($assignedBy);
        if (! in_array($dispute->status, [DisputeStatus::Submitted, DisputeStatus::UnderReview], true)) {
            throw new DomainStateTransitionException('Reviewers can only be assigned to active disputes.');
        }
        if ($this->isOriginalParticipant($dispute->evaluation, $reviewer)) {
            throw new DomainStateTransitionException('A formal dispute reviewer cannot have participated in the original evaluation.');
        }
        if ($dispute->reviewers()->where('reviewer_id', $reviewer->id)->exists()) {
            throw new DomainStateTransitionException('The user is already assigned to this dispute.');
        }

        return DB::transaction(function () use ($dispute, $reviewer, $assignedBy): DisputeReviewer {
            $review = DisputeReviewer::query()->create([
                'dispute_id' => $dispute->id,
                'reviewer_id' => $reviewer->id,
                'assigned_by' => $assignedBy->id,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);
            if ($dispute->status === DisputeStatus::Submitted) {
                $dispute->status = DisputeStatus::UnderReview;
                $dispute->save();
            }
            AuditLogger::record(event: 'dispute.reviewer_assigned', auditable: $review, after: [
                'reviewer_id' => $reviewer->id,
                'assigned_by' => $assignedBy->id,
            ]);
            return $review->refresh();
        });
    }

    public function completeReview(DisputeReviewer $review, User $reviewer, string $notes): DisputeReviewer
    {
        if ($review->reviewer_id !== $reviewer->id) {
            throw new DomainStateTransitionException('Only the assigned dispute reviewer can complete this review.');
        }
        if ($review->status !== 'assigned') {
            throw new DomainStateTransitionException('This dispute review is no longer active.');
        }
        if (trim($notes) === '') {
            throw new DomainStateTransitionException('A dispute review requires review notes.');
        }

        return DB::transaction(function () use ($review, $reviewer, $notes): DisputeReviewer {
            $review = DisputeReviewer::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $review->status = 'completed';
            $review->completed_at = now();
            $review->review_notes = trim($notes);
            $review->save();
            AuditLogger::record(event: 'dispute.review_completed', auditable: $review, after: ['reviewer_id' => $reviewer->id]);
            return $review->refresh();
        });
    }

    public function resolve(Dispute $dispute, User $resolvedBy, DisputeOutcome $outcome, string $rationale): array
    {
        $this->requirePlatformAdmin($resolvedBy);
        if ($dispute->status !== DisputeStatus::UnderReview) {
            throw new DomainStateTransitionException('Only disputes under review can be resolved.');
        }
        if ($dispute->reviewers()->where('status', 'completed')->doesntExist()) {
            throw new DomainStateTransitionException('A formal dispute requires at least one completed independent review.');
        }
        if (trim($rationale) === '') {
            throw new DomainStateTransitionException('A formal dispute resolution requires a rationale.');
        }

        return DB::transaction(function () use ($dispute, $resolvedBy, $outcome, $rationale): array {
            $dispute = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $dispute->status = DisputeStatus::Resolved;
            $dispute->outcome = $outcome;
            $dispute->decision_rationale = trim($rationale);
            $dispute->resolved_at = now();
            $dispute->resolved_by = $resolvedBy->id;
            $dispute->save();

            $newEvaluation = null;
            if ($outcome === DisputeOutcome::ProcessFlawed) {
                $evaluation = $dispute->evaluation()->lockForUpdate()->firstOrFail();
                $newEvaluation = Evaluation::query()->create([
                    'evaluation_request_id' => $evaluation->evaluation_request_id,
                    'product_release_id' => $evaluation->product_release_id,
                    'standard_version_id' => $evaluation->standard_version_id,
                    'status' => EvaluationStatus::Pending,
                ]);
            }

            AuditLogger::record(event: 'dispute.resolved', auditable: $dispute, after: [
                'outcome' => $outcome->value,
                'resolved_by' => $resolvedBy->id,
                'new_evaluation_id' => $newEvaluation?->id,
            ]);
            return ['dispute' => $dispute->refresh(), 'new_evaluation' => $newEvaluation?->refresh()];
        });
    }

    private function isOriginalParticipant(Evaluation $evaluation, User $user): bool
    {
        return $evaluation->assignments()->where('auditor_id', $user->id)->exists()
            || $evaluation->decisions()->where('decided_by', $user->id)->exists()
            || $evaluation->findings()->whereHas('auditorEvaluation.assignment', fn ($query) => $query->where('auditor_id', $user->id))->exists();
    }

    private function creatorCanAct(Organization $organization, User $user): bool
    {
        return $organization->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin', 'editor'])->exists();
    }

    private function requirePlatformAdmin(User $user): void
    {
        if (! $user->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can manage formal disputes.');
        }
    }
}
