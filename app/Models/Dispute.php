<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeOutcome;
use App\Enums\DisputeStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DisputeStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $resolved_by
 * @property DisputeOutcome|null $outcome
 */
class Dispute extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id', 'organization_id', 'submitted_by', 'type', 'grounds', 'statement',
        'status', 'submitted_at', 'resolved_at', 'resolved_by', 'outcome', 'decision_rationale',
    ];

    protected function casts(): array
    {
        return [
            'grounds' => 'array',
            'status' => DisputeStatus::class,
            'outcome' => DisputeOutcome::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $dispute): void {
            $originalStatus = $dispute->getOriginal('status');

            if ($originalStatus === DisputeStatus::Resolved->value) {
                throw new DomainStateTransitionException('Resolved disputes are immutable.');
            }

            if (
                $originalStatus === DisputeStatus::Submitted->value
                && array_diff(array_keys($dispute->getDirty()), ['status'])
            ) {
                throw new DomainStateTransitionException('Submitted dispute content is immutable.');
            }
        });

        static::deleting(function (self $dispute): void {
            if ($dispute->submitted_at !== null) {
                throw new DomainStateTransitionException('Submitted disputes cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<DisputeReviewer, $this> */
    public function reviewers(): HasMany
    {
        return $this->hasMany(DisputeReviewer::class);
    }
}
