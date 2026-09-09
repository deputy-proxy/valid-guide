<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AudiencePromiseCoherence;
use App\Enums\EvidenceSufficiency;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $locked_at
 * @property EvidenceSufficiency|null $evidence_sufficiency
 * @property AudiencePromiseCoherence|null $audience_promise_coherence
 */
class AuditorEvaluation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'auditor_assignment_id',
        'version',
        'status',
        'submitted_at',
        'locked_at',
        'evidence_sufficiency',
        'audience_promise_coherence',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
            'evidence_sufficiency' => EvidenceSufficiency::class,
            'audience_promise_coherence' => AudiencePromiseCoherence::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $evaluation): void {
            if ($evaluation->getOriginal('locked_at') !== null) {
                throw new DomainStateTransitionException('A submitted auditor evaluation is immutable.');
            }
        });

        static::deleting(function (self $evaluation): void {
            if ($evaluation->locked_at !== null) {
                throw new DomainStateTransitionException('A submitted auditor evaluation is immutable.');
            }
        });
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<AuditorAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AuditorAssignment::class, 'auditor_assignment_id');
    }

    /** @return HasMany<CriterionResult, $this> */
    public function criterionResults(): HasMany
    {
        return $this->hasMany(CriterionResult::class);
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /** @return HasMany<Evidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
