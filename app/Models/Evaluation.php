<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property EvaluationStatus $status
 */
class Evaluation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_request_id',
        'product_id',
        'product_release_id',
        'standard_version_id',
        'status',
        'decision',
        'overall_score',
        'started_at',
        'submitted_at',
        'internal_reviewed_at',
        'completed_at',
        'published_at',
        'decision_rationale',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvaluationStatus::class,
            'overall_score' => 'decimal:2',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'internal_reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $evaluation): void {
            $productId = $evaluation->getAttribute('product_id');
            $productReleaseId = $evaluation->getAttribute('product_release_id');

            if ($productId === null && $productReleaseId !== null) {
                $productId = ProductRelease::query()->whereKey($productReleaseId)->value('product_id');
                $evaluation->setAttribute('product_id', $productId);
            }

            if ($productId === null) {
                throw new DomainStateTransitionException('An evaluation must persist the Product that was evaluated.');
            }

            $releaseProductId = ProductRelease::query()->whereKey($productReleaseId)->value('product_id');
            if ($releaseProductId !== (int) $productId) {
                throw new DomainStateTransitionException('An evaluation Product must match its Product Release.');
            }
        });

        static::updating(function (self $evaluation): void {
            if ($evaluation->isDirty('evaluation_request_id') || $evaluation->isDirty('product_id') || $evaluation->isDirty('product_release_id') || $evaluation->isDirty('standard_version_id')) {
                throw new DomainStateTransitionException('Evaluation provenance is immutable after creation.');
            }

            if ($evaluation->getOriginal('status') === EvaluationStatus::Completed->value) {
                throw new DomainStateTransitionException('Completed evaluations are immutable.');
            }

            if ($evaluation->getOriginal('decision') !== null && ($evaluation->isDirty('decision') || $evaluation->isDirty('overall_score') || $evaluation->isDirty('decision_rationale'))) {
                throw new DomainStateTransitionException('Recorded evaluation decisions are immutable.');
            }
        });
    }

    /** @return BelongsTo<EvaluationRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EvaluationRequest::class, 'evaluation_request_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductRelease, $this> */
    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    /** @return BelongsTo<StandardVersion, $this> */
    public function standardVersion(): BelongsTo
    {
        return $this->belongsTo(StandardVersion::class);
    }

    /** @return HasOne<Report, $this> */
    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    /** @return HasOne<Validation, $this> */
    public function validation(): HasOne
    {
        return $this->hasOne(Validation::class);
    }

    /** @return HasMany<AuditorAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(AuditorAssignment::class);
    }

    /** @return HasMany<AuditorEvaluation, $this> */
    public function auditorEvaluations(): HasMany
    {
        return $this->hasMany(AuditorEvaluation::class);
    }

    /** @return HasMany<CriterionVote, $this> */
    public function criterionVotes(): HasMany
    {
        return $this->hasMany(CriterionVote::class);
    }

    /** @return HasMany<EvaluationDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(EvaluationDecision::class);
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /** @return HasMany<CreatorAction, $this> */
    public function creatorActions(): HasMany
    {
        return $this->hasMany(CreatorAction::class);
    }

    /** @return HasMany<ImprovementGuidance, $this> */
    public function improvementGuidances(): HasMany
    {
        return $this->hasMany(ImprovementGuidance::class);
    }

    /** @return HasMany<ImprovementOpportunity, $this> */
    public function improvementOpportunities(): HasMany
    {
        return $this->hasMany(ImprovementOpportunity::class);
    }

    /** @return HasMany<Dispute, $this> */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /** @return HasMany<ClarificationRequest, $this> */
    public function clarificationRequests(): HasMany
    {
        return $this->hasMany(ClarificationRequest::class);
    }
}
