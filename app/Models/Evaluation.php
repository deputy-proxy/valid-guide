<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Evaluation extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_request_id', 'product_release_id', 'standard_version_id', 'status',
        'decision', 'overall_score', 'started_at', 'completed_at', 'published_at', 'decision_rationale',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvaluationStatus::class,
            'overall_score' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $evaluation): void {
            if ($evaluation->isDirty('evaluation_request_id') || $evaluation->isDirty('product_release_id') || $evaluation->isDirty('standard_version_id')) {
                throw new DomainStateTransitionException('Evaluation provenance is immutable after creation.');
            }

            if ($evaluation->getOriginal('status') === EvaluationStatus::Completed->value) {
                throw new DomainStateTransitionException('Completed evaluations are immutable.');
            }

            if ($evaluation->getOriginal('decision') !== null && (
                $evaluation->isDirty('decision')
                || $evaluation->isDirty('overall_score')
                || $evaluation->isDirty('decision_rationale')
            )) {
                throw new DomainStateTransitionException('Recorded evaluation decisions are immutable.');
            }
        });
    }

    /** @return BelongsTo<EvaluationRequest, Evaluation> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EvaluationRequest::class, 'evaluation_request_id');
    }

    /** @return BelongsTo<ProductRelease, Evaluation> */
    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    /** @return BelongsTo<StandardVersion, Evaluation> */
    public function standardVersion(): BelongsTo
    {
        return $this->belongsTo(StandardVersion::class);
    }

    /** @return HasOne<Report, Evaluation> */
    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    /** @return HasOne<Validation, Evaluation> */
    public function validation(): HasOne
    {
        return $this->hasOne(Validation::class);
    }

    /** @return HasMany<AuditorAssignment, Evaluation> */
    public function assignments(): HasMany
    {
        return $this->hasMany(AuditorAssignment::class);
    }

    /** @return HasMany<AuditorEvaluation, Evaluation> */
    public function auditorEvaluations(): HasMany
    {
        return $this->hasMany(AuditorEvaluation::class);
    }

    /** @return HasMany<CriterionVote, Evaluation> */
    public function criterionVotes(): HasMany
    {
        return $this->hasMany(CriterionVote::class);
    }

    /** @return HasMany<EvaluationDecision, Evaluation> */
    public function decisions(): HasMany
    {
        return $this->hasMany(EvaluationDecision::class);
    }

    /** @return HasMany<Finding, Evaluation> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /** @return HasMany<Dispute, Evaluation> */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /** @return HasMany<ClarificationRequest, Evaluation> */
    public function clarificationRequests(): HasMany
    {
        return $this->hasMany(ClarificationRequest::class);
    }
}
