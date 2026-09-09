<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Enums\OrganizationRole;
use App\Models\EvaluationMaterial;
use App\Models\EvaluationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EvaluationMaterialIntake
{
    /** @var list<EvaluationRequestStatus> */
    private const SUBMISSION_STATES = [
        EvaluationRequestStatus::Paid,
        EvaluationRequestStatus::Intake,
        EvaluationRequestStatus::AwaitingCreator,
    ];

    /** @param array<string, mixed>|null $metadata */
    /** @param array<string, mixed>|null $metadata */
    public function submit(
        EvaluationRequest $request,
        User $submittedBy,
        EvaluationMaterialType $type,
        string $label,
        ?string $description = null,
        ?string $location = null,
        ?array $metadata = null,
    ): EvaluationMaterial {
        if (! in_array($request->status, self::SUBMISSION_STATES, true)) {
            throw new DomainStateTransitionException('Materials can only be submitted during evaluation intake.');
        }

        if (! $request->organization->hasMemberWithRole($submittedBy, OrganizationRole::Owner)
            && ! $request->organization->hasMemberWithRole($submittedBy, OrganizationRole::Admin)
            && ! $request->organization->hasMemberWithRole($submittedBy, OrganizationRole::Editor)) {
            throw new DomainStateTransitionException('Only authorized organization members can submit evaluation materials.');
        }

        if (trim($label) === '') {
            throw new DomainStateTransitionException('Evaluation material labels are required.');
        }

        if ($type !== EvaluationMaterialType::Note && blank($location)) {
            throw new DomainStateTransitionException('A location is required for files, URLs and access materials.');
        }

        return DB::transaction(function () use ($request, $submittedBy, $type, $label, $description, $location, $metadata): EvaluationMaterial {
            $request = EvaluationRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($request->status, self::SUBMISSION_STATES, true)) {
                throw new DomainStateTransitionException('Materials can only be submitted during evaluation intake.');
            }

            $material = EvaluationMaterial::query()->create([
                'evaluation_request_id' => $request->id,
                'submitted_by' => $submittedBy->id,
                'type' => $type,
                'label' => trim($label),
                'description' => $description,
                'location' => $location,
                'metadata' => $metadata,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            AuditLogger::record(
                event: 'evaluation_material.submitted',
                auditable: $material,
                after: [
                    'evaluation_request_id' => $request->id,
                    'type' => $type->value,
                    'label' => $material->label,
                ],
            );

            return $material->refresh();
        });
    }

    public function verify(
        EvaluationMaterial $material,
        User $verifiedBy,
        string $notes,
    ): EvaluationMaterial {
        if (! $verifiedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can verify evaluation materials.');
        }

        if ($material->verified_at !== null) {
            throw new DomainStateTransitionException('Evaluation material has already been verified.');
        }

        if (trim($notes) === '') {
            throw new DomainStateTransitionException('Material verification requires notes.');
        }

        return DB::transaction(function () use ($material, $verifiedBy, $notes): EvaluationMaterial {
            $material = EvaluationMaterial::query()->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
            /** @var EvaluationMaterial $material */
            if ($material->verified_at !== null) {
                throw new DomainStateTransitionException('Evaluation material has already been verified.');
            }
            $material->status = 'verified';
            $material->verified_at = now();
            $material->verified_by = $verifiedBy->id;
            $material->verification_notes = trim($notes);
            $material->save();

            AuditLogger::record(
                event: 'evaluation_material.verified',
                auditable: $material,
                after: [
                    'verified_by' => $verifiedBy->id,
                    'status' => 'verified',
                ],
            );

            return $material->refresh();
        });
    }
}
