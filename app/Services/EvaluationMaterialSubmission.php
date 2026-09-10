<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationMaterial;
use App\Models\EvaluationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class EvaluationMaterialSubmission
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function submit(
        User $actor,
        EvaluationRequest $request,
        EvaluationMaterialType $type,
        string $label,
        ?string $description = null,
        ?string $location = null,
        ?array $metadata = null,
    ): EvaluationMaterial {
        Gate::forUser($actor)->authorize('view', $request);

        return DB::transaction(function () use ($actor, $request, $type, $label, $description, $location, $metadata): EvaluationMaterial {
            $request = EvaluationRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSubmissionAllowed($request);

            $label = trim($label);
            $description = $description !== null ? trim($description) : null;
            $location = $location !== null ? trim($location) : null;

            if ($label === '') {
                throw new DomainStateTransitionException('An evaluation material label is required.');
            }

            if (Str::length($label) > 255) {
                throw new DomainStateTransitionException('An evaluation material label must not exceed 255 characters.');
            }

            if ($description !== null && Str::length($description) > 10000) {
                throw new DomainStateTransitionException('An evaluation material description must not exceed 10000 characters.');
            }

            if (in_array($type, [EvaluationMaterialType::File, EvaluationMaterialType::Url, EvaluationMaterialType::Access], true) && blank($location)) {
                throw new DomainStateTransitionException('This evaluation material type requires a location.');
            }

            $material = EvaluationMaterial::query()->create([
                'evaluation_request_id' => $request->getKey(),
                'submitted_by' => $actor->getKey(),
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
                    'submitted_by' => $actor->getKey(),
                    'submitted_at' => $material->submitted_at?->toIso8601String(),
                ],
                actor: $actor,
            );

            return $material->refresh();
        });
    }

    private function assertSubmissionAllowed(EvaluationRequest $request): void
    {
        $allowedStatuses = [
            EvaluationRequestStatus::Paid,
            EvaluationRequestStatus::Intake,
            EvaluationRequestStatus::AwaitingCreator,
        ];

        if (in_array($request->status, $allowedStatuses, true) === false) {
            throw new DomainStateTransitionException('Evaluation materials can only be submitted during the paid intake workflow.');
        }
    }
}
