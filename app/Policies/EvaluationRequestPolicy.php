<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\EvaluationRequest;
use App\Models\User;

class EvaluationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organizations()->exists();
    }

    public function view(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $evaluationRequest->organization->users()->whereKey($user->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->organizations()->wherePivotIn('role', [
            OrganizationRole::Owner->value,
            OrganizationRole::Admin->value,
            OrganizationRole::Editor->value,
        ])->exists();
    }

    public function update(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $evaluationRequest->organization->hasMemberWithRole($user, OrganizationRole::Owner)
            || $evaluationRequest->organization->hasMemberWithRole($user, OrganizationRole::Admin)
            || $evaluationRequest->organization->hasMemberWithRole($user, OrganizationRole::Editor);
    }

    public function delete(User $user, EvaluationRequest $evaluationRequest): bool
    {
        return $this->update($user, $evaluationRequest)
            && $evaluationRequest->status->value === 'draft';
    }
}
