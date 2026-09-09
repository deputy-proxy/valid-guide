<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CriterionAssessment;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterionResult extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_evaluation_id',
        'criterion_id',
        'assessment',
        'score',
        'rationale',
        'confidence',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'assessment' => CriterionAssessment::class,
            'score' => 'decimal:2',
            'confidence' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $result): void {
            $assessment = $result->assessment;
            if (! $assessment instanceof CriterionAssessment) {
                throw new DomainStateTransitionException('A criterion result must use an allowed methodology assessment.');
            }

            $standardVersion = $result->criterion()->firstOrFail()->standardVersion()->firstOrFail();
            $anchor = $standardVersion->scoreAnchorFor($assessment);

            if (! $assessment->isScored()) {
                if ($result->score !== null) {
                    throw new DomainStateTransitionException(sprintf(
                        'Assessment %s must not have a numerical score.',
                        $assessment->value,
                    ));
                }

                return;
            }

            if ($result->score === null) {
                throw new DomainStateTransitionException(sprintf(
                    'Assessment %s requires a numerical score.',
                    $assessment->value,
                ));
            }

            $score = (float) $result->score;
            if ($anchor === null || $score < $anchor['min'] || $score > $anchor['max']) {
                throw new DomainStateTransitionException(sprintf(
                    'Score %.2f is outside the methodology range for assessment %s.',
                    $score,
                    $assessment->value,
                ));
            }
        });

        static::updating(function (self $result): void {
            if ($result->auditorEvaluation()->whereNotNull('locked_at')->exists()) {
                throw new DomainStateTransitionException('Criterion results are immutable after auditor submission.');
            }
        });

        static::deleting(function (self $result): void {
            if ($result->auditorEvaluation()->whereNotNull('locked_at')->exists()) {
                throw new DomainStateTransitionException('Criterion results are immutable after auditor submission.');
            }
        });
    }

    /** @return BelongsTo<AuditorEvaluation, $this> */
    public function auditorEvaluation(): BelongsTo
    {
        return $this->belongsTo(AuditorEvaluation::class);
    }

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    /** @return HasMany<Evidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
