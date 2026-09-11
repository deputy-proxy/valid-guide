<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementOpportunityStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CreatorActionPriority $priority
 * @property ImprovementOpportunityStatus $status
 * @property int|null $assigned_to
 * @property int|null $superseded_by_id
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable|null $superseded_at
 */
class ImprovementOpportunity extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'evaluation_id',
        'product_release_id',
        'finding_id',
        'improvement_guidance_id',
        'created_by',
        'assigned_to',
        'superseded_by_id',
        'title',
        'target_outcome',
        'evidence_required',
        'completion_evidence',
        'priority',
        'status',
        'due_at',
        'completed_at',
        'dismissed_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => CreatorActionPriority::class,
            'status' => ImprovementOpportunityStatus::class,
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $opportunity): void {
            $evaluation = Evaluation::query()->with('request')->find($opportunity->evaluation_id);

            if ($evaluation === null || $evaluation->request?->organization_id !== $opportunity->organization_id) {
                throw new DomainStateTransitionException('Improvement opportunity must belong to the evaluation organization.');
            }

            if ($evaluation->status->value !== 'completed') {
                throw new DomainStateTransitionException('Improvement opportunities can only be created for completed evaluations.');
            }

            if ($opportunity->product_release_id !== null && $opportunity->product_release_id !== $evaluation->product_release_id) {
                throw new DomainStateTransitionException('Improvement opportunity release must belong to the evaluation.');
            }

            if ($opportunity->finding_id !== null && ! Finding::query()
                ->whereKey($opportunity->finding_id)
                ->where('evaluation_id', $opportunity->evaluation_id)
                ->exists()) {
                throw new DomainStateTransitionException('Improvement opportunity finding must belong to the evaluation.');
            }

            if ($opportunity->improvement_guidance_id !== null && ! ImprovementGuidance::query()
                ->whereKey($opportunity->improvement_guidance_id)
                ->where('evaluation_id', $opportunity->evaluation_id)
                ->where('organization_id', $opportunity->organization_id)
                ->exists()) {
                throw new DomainStateTransitionException('Improvement opportunity guidance must belong to the evaluation organization.');
            }

            $opportunity->status ??= ImprovementOpportunityStatus::Open;
        });

        static::updating(function (self $opportunity): void {
            foreach ([
                'organization_id',
                'evaluation_id',
                'product_release_id',
                'finding_id',
                'improvement_guidance_id',
                'created_by',
                'title',
                'target_outcome',
                'evidence_required',
            ] as $attribute) {
                if ($opportunity->isDirty($attribute)) {
                    throw new DomainStateTransitionException('Improvement opportunity provenance and definition are immutable.');
                }
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<ProductRelease, $this> */
    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /** @return BelongsTo<ImprovementGuidance, $this> */
    public function improvementGuidance(): BelongsTo
    {
        return $this->belongsTo(ImprovementGuidance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<self, $this> */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /** @return HasOne<CreatorAction, $this> */
    public function creatorAction(): HasOne
    {
        return $this->hasOne(CreatorAction::class);
    }
}
