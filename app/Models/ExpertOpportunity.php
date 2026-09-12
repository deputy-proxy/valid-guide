<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpertOpportunityStatus;
use App\Enums\ExpertOpportunityType;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property ExpertOpportunityType $type */
class ExpertOpportunity extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'type',
        'expertise_areas',
        'product_types',
        'workload',
        'starts_at',
        'ends_at',
        'application_deadline',
        'eligibility_constraints',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'closed_at',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExpertOpportunityType::class,
            'status' => ExpertOpportunityStatus::class,
            'expertise_areas' => 'array',
            'product_types' => 'array',
            'eligibility_constraints' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'application_deadline' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $opportunity): void {
            if ($opportunity->getRawOriginal('status') !== ExpertOpportunityStatus::Draft->value) {
                $allowed = ['status', 'published_by', 'published_at', 'closed_at', 'cancelled_at', 'completed_at', 'updated_at'];
                $changed = array_diff(array_keys($opportunity->getDirty()), $allowed);

                if ($changed !== []) {
                    throw new DomainStateTransitionException('Published opportunity details are immutable.');
                }
            }
        });

        static::deleting(function (self $opportunity): void {
            if ($opportunity->participations()->exists()) {
                throw new DomainStateTransitionException('Opportunities with participation history cannot be deleted.');
            }

            if ($opportunity->status !== ExpertOpportunityStatus::Draft) {
                throw new DomainStateTransitionException('Published opportunities cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<ExpertOpportunityParticipation, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(ExpertOpportunityParticipation::class);
    }
}
