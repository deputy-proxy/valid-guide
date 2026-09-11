<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementGuidanceCategory;
use App\Enums\ImprovementGuidanceStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ImprovementGuidanceCategory $category
 * @property ImprovementGuidanceStatus $status
 * @property CreatorActionPriority $priority
 * @property array<int|string, mixed>|null $applicability
 * @property CarbonImmutable|null $creator_visible_at
 */
class ImprovementGuidance extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'evaluation_id',
        'report_id',
        'report_version_id',
        'finding_id',
        'criterion_result_id',
        'created_by',
        'category',
        'title',
        'guidance',
        'rationale',
        'priority',
        'applicability',
        'requires_action',
        'status',
        'creator_visible_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ImprovementGuidanceCategory::class,
            'status' => ImprovementGuidanceStatus::class,
            'priority' => CreatorActionPriority::class,
            'applicability' => 'array',
            'requires_action' => 'boolean',
            'creator_visible_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $guidance): void {
            $evaluation = Evaluation::query()->with('request')->find($guidance->evaluation_id);

            if ($evaluation === null || $evaluation->request?->organization_id !== $guidance->organization_id) {
                throw new DomainStateTransitionException('Improvement guidance must belong to the evaluation organization.');
            }

            if ($evaluation->status->value !== 'completed') {
                throw new DomainStateTransitionException('Improvement guidance can only be created for completed evaluations.');
            }

            if ($guidance->finding_id !== null && ! Finding::query()
                ->whereKey($guidance->finding_id)
                ->where('evaluation_id', $guidance->evaluation_id)
                ->exists()) {
                throw new DomainStateTransitionException('Improvement guidance finding must belong to the evaluation.');
            }

            if ($guidance->report_id !== null && ! Report::query()
                ->whereKey($guidance->report_id)
                ->where('evaluation_id', $guidance->evaluation_id)
                ->exists()) {
                throw new DomainStateTransitionException('Improvement guidance report must belong to the evaluation.');
            }

            $guidance->status ??= ImprovementGuidanceStatus::Draft;
        });

        static::updating(function (self $guidance): void {
            foreach ([
                'organization_id',
                'evaluation_id',
                'report_id',
                'report_version_id',
                'finding_id',
                'criterion_result_id',
                'created_by',
                'category',
                'title',
                'guidance',
                'rationale',
                'priority',
                'applicability',
                'requires_action',
            ] as $attribute) {
                if ($guidance->isDirty($attribute)) {
                    throw new DomainStateTransitionException('Improvement guidance content and provenance are immutable.');
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

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<ReportVersion, $this> */
    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class);
    }

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /** @return BelongsTo<CriterionResult, $this> */
    public function criterionResult(): BelongsTo
    {
        return $this->belongsTo(CriterionResult::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<CreatorAction, $this> */
    public function creatorAction(): HasOne
    {
        return $this->hasOne(CreatorAction::class);
    }
}
