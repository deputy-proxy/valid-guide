<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Models\EvaluationRequest;
use App\Models\Organization;
use App\Models\User;

class EvaluationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->organizations()
            ->wherePivotIn('role', $this->creatorRoles())
            ->exists();
    }

    public function view(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $user->isPlatformAdmin()
            || $this->canManageCreatorResource($user, $evaluationRequest->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->canManageCreatorResource($user, $organization);
    }

    public function update(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $evaluationRequest->status === EvaluationRequestStatus::Draft
            && $this->canManageCreatorResource($user, $evaluationRequest->organization);
    }

    public function submit(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $evaluationRequest->status === EvaluationRequestStatus::Draft
            && $this->canManageCreatorResource($user, $evaluationRequest->organization);
    }

    public function cancel(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return in_array($evaluationRequest->status, [
            EvaluationRequestStatus::AwaitingPayment,
            EvaluationRequestStatus::AwaitingCreator,
        ], true) && $this->canManageCreatorResource($user, $evaluationRequest->organization);
    }

    public function refund(User $user, EvaluationRequest $evaluationRequest): bool
    {
        $eligibleLifecycle = in_array($evaluationRequest->status, [
            EvaluationRequestStatus::Paid,
            EvaluationRequestStatus::Intake,
            EvaluationRequestStatus::AwaitingCreator,
            EvaluationRequestStatus::Ready,
        ], true);

        if (! $eligibleLifecycle) {
            return false;
        }

        return $user->isPlatformAdmin()
            || $this->canManageCreatorResource($user, $evaluationRequest->organization);
    }

    public function delete(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $evaluationRequest->status === EvaluationRequestStatus::Draft
            && $this->canManageCreatorResource($user, $evaluationRequest->organization);
    }

    /** @return list<string> */
    private function creatorRoles(): array
    {
        return [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
            OrganizationRole::Editor->value,
        ];
    }

    private function canManageCreatorResource(User $user, Organization $organization): bool
    {
        return $organization->users()
            ->whereKey($user->getKey())
            ->wherePivotIn('role', $this->creatorRoles())
            ->exists();
    }
}
