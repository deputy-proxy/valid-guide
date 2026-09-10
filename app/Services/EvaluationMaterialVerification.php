<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EvaluationMaterial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EvaluationMaterialVerification
{
    public function verify(User $actor, EvaluationMaterial $material, ?string $notes = null): EvaluationMaterial
    {
        if (! $actor->isPlatformAdmin()) {
            throw new AuthorizationException('Only platform administrators can verify evaluation materials.');
        }

        return DB::transaction(function () use ($actor, $material, $notes): EvaluationMaterial {
            $material = EvaluationMaterial::query()
                ->whereKey($material->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($material->submitted_at === null || $material->submitted_by === null) {
                throw new DomainStateTransitionException('Only submitted evaluation materials can be verified.');
            }

            if ($material->verified_at !== null || $material->status === 'verified') {
                throw new DomainStateTransitionException('An evaluation material can only be verified once.');
            }

            $notes = $notes !== null ? trim($notes) : null;
            if ($notes !== null && Str::length($notes) > 10000) {
                throw new DomainStateTransitionException('Verification notes must not exceed 10000 characters.');
            }

            $now = now();
            EvaluationMaterial::query()
                ->whereKey($material->getKey())
                ->update([
                    'status' => 'verified',
                    'verified_at' => $now,
                    'verified_by' => $actor->getKey(),
                    'verification_notes' => $notes,
                    'updated_at' => $now,
                ]);

            $material->refresh();

            AuditLogger::record(
                event: 'evaluation_material.verified',
                auditable: $material,
                before: ['status' => 'submitted', 'verified_at' => null, 'verified_by' => null],
                after: [
                    'status' => 'verified',
                    'verified_at' => $material->verified_at?->toIso8601String(),
                    'verified_by' => $actor->getKey(),
                ],
                metadata: ['verification_notes' => $notes],
                actor: $actor,
            );

            return $material;
        });
    }
}
