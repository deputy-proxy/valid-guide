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
use Illuminate\Support\Str;

final class EvaluationMaterialIntake
{
    /** @var list<EvaluationRequestStatus> */
    private const SUBMISSION_STATES = [
        EvaluationRequestStatus::Paid,
        EvaluationRequestStatus::Intake,
        EvaluationRequestStatus::AwaitingCreator,
    ];

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
        if (in_array($request->status, self::SUBMISSION_STATES, true) === false) {
            throw new DomainStateTransitionException('Materials can only be submitted during the paid intake workflow.');
        }

        if ($request->organization->hasMemberWithRole($submittedBy, OrganizationRole::Owner) === false
            && $request->organization->hasMemberWithRole($submittedBy, OrganizationRole::Admin) === false
            && $request->organization->hasMemberWithRole($submittedBy, OrganizationRole::Editor) === false) {
            throw new DomainStateTransitionException('Only authorized organization members can submit evaluation materials.');
        }

        $label = trim($label);
        $description = $description !== null ? trim($description) : null;
        $location = $location !== null ? trim($location) : null;

        if ($label === '') {
            throw new DomainStateTransitionException('Evaluation material labels are required.');
        }

        if (Str::length($label) > 255) {
            throw new DomainStateTransitionException('Evaluation material labels must not exceed 255 characters.');
        }

        if ($description !== null && Str::length($description) > 10000) {
            throw new DomainStateTransitionException('Evaluation material descriptions must not exceed 10000 characters.');
        }

        if (in_array($type, [EvaluationMaterialType::File, EvaluationMaterialType::Url, EvaluationMaterialType::Access], true)
            && blank($location)) {
            throw new DomainStateTransitionException('A location is required for files, URLs and access materials.');
        }

        return DB::transaction(function () use ($request, $submittedBy, $type, $label, $description, $location, $metadata): EvaluationMaterial {
            $request = EvaluationRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($request->status, self::SUBMISSION_STATES, true) === false) {
                throw new DomainStateTransitionException('Materials can only be submitted during the paid intake workflow.');
            }

            $material = EvaluationMaterial::query()->create([
                'evaluation_request_id' => $request->getKey(),
                'submitted_by' => $submittedBy->getKey(),
                'type' => $type,
                'label' => $label,
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
                    'evaluation_request_id' => $request->getKey(),
                    'type' => $type->value,
                    'label' => $material->label,
                ],
                actor: $submittedBy,
            );

            return $material->refresh();
        });
    }

    public function verify(
        EvaluationMaterial $material,
        User $verifiedBy,
        string $notes,
    ): EvaluationMaterial {
        if ($verifiedBy->isPlatformAdmin() === false) {
            throw new DomainStateTransitionException('Only platform administrators can verify evaluation materials.');
        }

        $notes = trim($notes);
        if ($notes === '') {
            throw new DomainStateTransitionException('Material verification requires notes.');
        }

        if (Str::length($notes) > 10000) {
            throw new DomainStateTransitionException('Material verification notes must not exceed 10000 characters.');
        }

        return DB::transaction(function () use ($material, $verifiedBy, $notes): EvaluationMaterial {
            $material = EvaluationMaterial::query()
                ->whereKey($material->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($material->submitted_at === null || $material->submitted_by === null) {
                throw new DomainStateTransitionException('Only submitted evaluation materials can be verified.');
            }

            if ($material->verified_at !== null || $material->status === 'verified') {
                throw new DomainStateTransitionException('Evaluation material has already been verified.');
            }

            $now = now();
            EvaluationMaterial::query()
                ->whereKey($material->getKey())
                ->update([
                    'status' => 'verified',
                    'verified_at' => $now,
                    'verified_by' => $verifiedBy->getKey(),
                    'verification_notes' => $notes,
                    'updated_at' => $now,
                ]);

            $material->refresh();

            AuditLogger::record(
                event: 'evaluation_material.verified',
                auditable: $material,
                before: [
                    'status' => 'submitted',
                    'verified_at' => null,
                    'verified_by' => null,
                ],
                after: [
                    'status' => 'verified',
                    'verified_by' => $verifiedBy->getKey(),
                ],
                metadata: ['verification_notes' => $notes],
                actor: $verifiedBy,
            );

            return $material;
        });
    }
}
