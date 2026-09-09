<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CriterionAssessment;
use App\Enums\StandardVersionStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property StandardVersionStatus $status
 * @property CarbonImmutable|null $effective_at
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable|null $approved_at
 * @property int|null $approved_by
 * @property array<string,mixed>|null $score_anchors
 * @property array<string,mixed>|null $decision_thresholds
 */
class StandardVersion extends Model
{
    /** @var array<string,array{min:int|null,max:int|null}> */
    public const DEFAULT_SCORE_ANCHORS = [
        'exceeds' => ['min' => 90, 'max' => 100],
        'meets' => ['min' => 75, 'max' => 89],
        'partially_meets' => ['min' => 50, 'max' => 74],
        'does_not_meet' => ['min' => 0, 'max' => 49],
        'insufficient_evidence' => ['min' => null, 'max' => null],
        'not_applicable' => ['min' => null, 'max' => null],
    ];

    /** @var array<string,int> */
    public const DEFAULT_DECISION_THRESHOLDS = [
        'overall_minimum' => 75,
        'mandatory_minimum' => 75,
        'dimension_minimum' => 60,
    ];

    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_standard_id', 'version', 'description', 'effective_at', 'retired_at',
        'status', 'approved_by', 'approved_at', 'score_anchors', 'decision_thresholds',
    ];

    protected function casts(): array
    {
        return [
            'status' => StandardVersionStatus::class,
            'effective_at' => 'datetime',
            'retired_at' => 'datetime',
            'approved_at' => 'datetime',
            'score_anchors' => 'array',
            'decision_thresholds' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->score_anchors ??= self::DEFAULT_SCORE_ANCHORS;
            $version->decision_thresholds ??= self::DEFAULT_DECISION_THRESHOLDS;
        });

        static::updating(function (self $version): void {
            $status = $version->getRawOriginal('status');
            if (in_array($status, [
                StandardVersionStatus::Scheduled->value,
                StandardVersionStatus::Effective->value,
                StandardVersionStatus::Retired->value,
            ], true)) {
                $lifecycleFields = ['status', 'effective_at', 'retired_at', 'approved_by', 'approved_at'];
                if (array_diff(array_keys($version->getDirty()), $lifecycleFields)) {
                    throw new DomainStateTransitionException('Scheduled, effective and retired standard version content is immutable.');
                }
            }
        });
        static::deleting(function (self $version): void {
            if ($version->status !== StandardVersionStatus::Draft) {
                throw new DomainStateTransitionException('Only draft standard versions may be deleted.');
            }
        });
    }

    /** @return array{min:int,max:int}|null */
    public function scoreAnchorFor(CriterionAssessment $assessment): ?array
    {
        $anchors = $this->score_anchors;
        if (! is_array($anchors)) {
            throw new DomainStateTransitionException(sprintf(
                'Standard version %s has no score anchors.',
                $this->version,
            ));
        }

        $anchor = $anchors[$assessment->value] ?? null;

        if (! $assessment->isScored()) {
            return null;
        }

        if (! is_array($anchor) || ! isset($anchor['min'], $anchor['max']) || ! is_int($anchor['min']) || ! is_int($anchor['max'])) {
            throw new DomainStateTransitionException(sprintf(
                'Standard version %s has no valid score anchor for %s.',
                $this->version,
                $assessment->value,
            ));
        }

        return [
            'min' => $anchor['min'],
            'max' => $anchor['max'],
        ];
    }

    /** @return array<string,mixed> */
    public function decisionThresholds(): array
    {
        $thresholds = $this->decision_thresholds;
        if (! is_array($thresholds)) {
            throw new DomainStateTransitionException(sprintf(
                'Standard version %s has no decision thresholds.',
                $this->version,
            ));
        }

        return $thresholds;
    }

    public function decisionThreshold(string $key): float
    {
        $threshold = $this->decisionThresholds()[$key] ?? null;
        if (! is_int($threshold) && ! is_float($threshold)) {
            throw new DomainStateTransitionException(sprintf(
                'Standard version %s has no valid decision threshold for %s.',
                $this->version,
                $key,
            ));
        }

        return (float) $threshold;
    }

    /** @return BelongsTo<EvaluationStandard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(EvaluationStandard::class, 'evaluation_standard_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /** @return HasMany<Criterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class);
    }
}
