<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpertOpportunityParticipationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ExpertOpportunityParticipationStatus $status
 * @property CarbonImmutable|null $conflict_determined_at
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable|null $selected_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 */
class ExpertOpportunityParticipation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'expert_opportunity_id',
        'auditor_profile_id',
        'status',
        'application_note',
        'conflict_disclosure',
        'conflict_outcome',
        'conflict_determined_by',
        'conflict_determined_at',
        'reviewed_by',
        'reviewed_at',
        'decision_reason',
        'applied_at',
        'selected_at',
        'accepted_at',
        'withdrawn_at',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpertOpportunityParticipationStatus::class,
            'conflict_determined_at' => 'datetime',
            'applied_at' => 'datetime',
            'selected_at' => 'datetime',
            'accepted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $participation): void {
            if ($participation->isDirty('expert_opportunity_id') || $participation->isDirty('auditor_profile_id')) {
                throw new DomainStateTransitionException('Opportunity participation provenance is immutable.');
            }

            if ($participation->getRawOriginal('conflict_determined_at') !== null && ($participation->isDirty('conflict_disclosure') || $participation->isDirty('conflict_outcome') || $participation->isDirty('conflict_determined_by') || $participation->isDirty('conflict_determined_at'))) {
                throw new DomainStateTransitionException('A determined opportunity conflict is immutable.');
            }

            if ($participation->getRawOriginal('completed_at') !== null && $participation->isDirty()) {
                throw new DomainStateTransitionException('Completed opportunity participation is immutable.');
            }
        });
    }

    /** @return BelongsTo<ExpertOpportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(ExpertOpportunity::class, 'expert_opportunity_id');
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function auditorProfile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function conflictDeterminedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conflict_determined_by');
    }
}
