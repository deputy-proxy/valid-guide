<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditorProfileStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditorAssignment;
use App\Models\ConflictDeclaration;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditorAssignmentCreation
{
    public function create(
        Evaluation $evaluation,
        User $auditor,
        User $assignedBy,
        int $sequence,
        int $compensationAmountMinor,
        string $compensationCurrency,
        ?Carbon $dueAt = null,
    ): AuditorAssignment {
        if (! $assignedBy->isPlatformAdmin()) {
            throw new DomainStateTransitionException('Only platform administrators can assign Auditors.');
        }

        if ($sequence < 1) {
            throw new DomainStateTransitionException('Auditor assignment sequence must be positive.');
        }

        if ($compensationAmountMinor <= 0 || ! preg_match('/^[A-Z]{3}$/', $compensationCurrency)) {
            throw new DomainStateTransitionException('Auditor compensation requires a positive amount and ISO currency code.');
        }

        if ($dueAt?->isPast()) {
            throw new DomainStateTransitionException('An Auditor assignment deadline must be in the future.');
        }

        try {
            return DB::transaction(function () use (
                $evaluation,
                $auditor,
                $assignedBy,
                $sequence,
                $compensationAmountMinor,
                $compensationCurrency,
                $dueAt,
            ): AuditorAssignment {
                $evaluation = Evaluation::query()
                    ->with(['productRelease.product', 'assignments'])
                    ->lockForUpdate()
                    ->findOrFail($evaluation->getKey());
                /** @var Evaluation $evaluation */
                if ($evaluation->status->value !== 'pending' && $evaluation->status->value !== 'in_progress') {
                    throw new DomainStateTransitionException('Auditors can only be assigned to pending or in-progress evaluations.');
                }
                app(AuditorStaffing::class)->assertCanAdd($evaluation);
                $this->assertEligible($evaluation, $auditor, $assignedBy);
                if ($evaluation->assignments()->where('auditor_id', $auditor->id)->exists()) {
                    throw new DomainStateTransitionException('The same Auditor cannot be assigned twice to one evaluation.');
                }
                if ($evaluation->assignments()->where('sequence', $sequence)->exists()) {
                    throw new DomainStateTransitionException('The requested Auditor assignment sequence is already occupied.');
                }

                $assignment = AuditorAssignment::query()->create([
                    'evaluation_id' => $evaluation->id,
                    'auditor_id' => $auditor->id,
                    'sequence' => $sequence,
                    'status' => 'offered',
                    'assigned_at' => now(),
                    'due_at' => $dueAt,
                    'compensation_amount_minor' => $compensationAmountMinor,
                    'compensation_currency' => $compensationCurrency,
                    'compensation_status' => 'pending',
                ]);

                ConflictDeclaration::query()->create([
                    'evaluation_id' => $evaluation->id,
                    'auditor_assignment_id' => $assignment->id,
                    'declaration_type' => 'assignment',
                    'outcome' => 'potential_conflict',
                ]);

                app(AuditorCompensationService::class)->assign(
                    $assignment,
                    $assignedBy,
                    $compensationAmountMinor,
                    $compensationCurrency,
                );

                AuditLogger::record(
                    event: 'auditor_assignment.created',
                    auditable: $assignment,
                    after: [
                        'evaluation_id' => $evaluation->id,
                        'auditor_id' => $auditor->id,
                        'sequence' => $sequence,
                        'due_at' => $dueAt?->toIso8601String(),
                    ],
                );

                return $assignment->refresh();
            });
        } catch (PriorProductParticipationException $exception) {
            AuditLogger::record(
                event: 'auditor_assignment.conflict_detected',
                auditable: $exception->evaluation,
                after: [
                    'auditor_id' => $exception->auditor->id,
                    'conflict' => 'prior_product_participation',
                ],
                metadata: [
                    'determined_by' => $exception->determinedBy->id,
                ],
            );

            throw $exception;
        }
    }

    public function isEligible(Evaluation $evaluation, User $auditor): bool
    {
        try {
            $this->assertEligible($evaluation, $auditor);
        } catch (DomainStateTransitionException) {
            return false;
        }

        return true;
    }

    private function assertEligible(Evaluation $evaluation, User $auditor, ?User $determinedBy = null): void
    {
        $profile = $auditor->auditorProfile()->with('competencies')->first();

        if ($profile === null || $profile->status !== AuditorProfileStatus::Approved) {
            throw new DomainStateTransitionException('The Auditor does not have an approved Auditor profile.');
        }

        if (! $profile->methodology_literate) {
            throw new DomainStateTransitionException('The Auditor has not been approved as methodology-literate.');
        }

        $product = $evaluation->productRelease?->product;

        if ($product === null || blank($product->subject_area)) {
            throw new DomainStateTransitionException('The product must have a subject area before an Auditor can be assigned.');
        }

        $formats = $profile->format_experience ?? [];
        if (! in_array($product->product_type->value, $formats, true)) {
            throw new DomainStateTransitionException('The Auditor does not have approved experience evaluating this product format.');
        }

        $topic = mb_strtolower(trim($product->subject_area));
        $hasVerifiedCompetency = $profile->competencies->contains(
            fn ($competency): bool => $competency->verified_at !== null
                && mb_strtolower(trim($competency->topic)) === $topic,
        );

        if (! $hasVerifiedCompetency) {
            throw new DomainStateTransitionException('The Auditor does not have verified subject-matter competence for this product.');
        }

        if (! app(AuditorAnnualConflictDeclarationService::class)->isCurrentAndCleared($auditor)) {
            throw new DomainStateTransitionException('The Auditor does not have a current annual conflict declaration cleared.');
        }

        if ($this->hasPriorProductParticipation($evaluation, $auditor)) {
            if ($determinedBy === null) {
                throw new DomainStateTransitionException('The Auditor cannot be assigned because they previously participated in this product.');
            }

            throw new PriorProductParticipationException($evaluation, $auditor, $determinedBy);
        }

        app(MarketplaceIndependence::class)->assertAuditorClear($evaluation, $auditor, $determinedBy ?? $auditor);
    }

    private function hasPriorProductParticipation(Evaluation $evaluation, User $auditor): bool
    {
        $productId = $evaluation->productRelease?->product_id;

        if ($productId === null) {
            return false;
        }

        $organizationId = DB::table('products')
            ->where('id', $productId)
            ->value('organization_id');

        if ($organizationId !== null && DB::table('organization_memberships')
            ->where('organization_id', $organizationId)
            ->where('user_id', $auditor->getKey())
            ->whereIn('role', [
                OrganizationRole::Owner->value,
                OrganizationRole::Admin->value,
                OrganizationRole::Editor->value,
            ])
            ->exists()) {
            return true;
        }

        return AuditorAssignment::query()
            ->where('auditor_id', $auditor->getKey())
            ->whereHas('evaluation.productRelease', function ($query) use ($productId): void {
                $query->where('product_id', $productId);
            })
            ->exists();
    }
}
