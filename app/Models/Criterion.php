<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StandardVersionStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string,mixed>|null $applicability_rules
 * @property array<string,mixed>|null $scoring_rules
 */
class Criterion extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'standard_version_id',
        'code',
        'name',
        'description',
        'category',
        'sequence',
        'weight',
        'is_mandatory',
        'applicability_rules',
        'scoring_rules',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'weight' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'applicability_rules' => 'array',
            'scoring_rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $criterion): void {
            $status = StandardVersion::query()->whereKey($criterion->standard_version_id)->value('status');

            if (in_array($status, [
                StandardVersionStatus::Scheduled->value,
                StandardVersionStatus::Effective->value,
                StandardVersionStatus::Retired->value,
            ], true)) {
                throw new DomainStateTransitionException(
                    'Criterion content is immutable once its standard version is scheduled.',
                );
            }
        });

        static::deleting(function (self $criterion): void {
            $status = StandardVersion::query()->whereKey($criterion->standard_version_id)->value('status');

            if ($status !== StandardVersionStatus::Draft->value) {
                throw new DomainStateTransitionException(
                    'Criteria may only be deleted while their standard version is draft.',
                );
            }
        });
    }

    /** @return BelongsTo<StandardVersion, $this> */
    public function standardVersion(): BelongsTo
    {
        return $this->belongsTo(StandardVersion::class);
    }

    /** @return HasMany<CriterionResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(CriterionResult::class);
    }

    /** @return HasMany<CriterionGuidance, $this> */
    public function guidance(): HasMany
    {
        return $this->hasMany(CriterionGuidance::class);
    }
}
