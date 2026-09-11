<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreatorActionPriority;
use App\Enums\CreatorActionStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CreatorActionPriority $priority
 * @property CreatorActionStatus $status
 * @property int|null $assigned_to
 * @property int|null $improvement_opportunity_id
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $completed_at
 */
class CreatorAction extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'evaluation_id',
        'finding_id',
        'improvement_guidance_id',
        'improvement_opportunity_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'priority',
        'status',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => CreatorActionPriority::class,
            'status' => CreatorActionStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $action): void {
            $evaluation = Evaluation::query()->find($action->evaluation_id);

            if ($evaluation === null || $evaluation->request?->organization_id !== $action->organization_id) {
                throw new DomainStateTransitionException('A creator action must belong to the evaluation organization.');
            }

            if ($action->finding_id !== null) {
                $finding = Finding::query()->find($action->finding_id);

                if ($finding === null || $finding->evaluation_id !== $action->evaluation_id) {
                    throw new DomainStateTransitionException('A creator action finding must belong to the action evaluation.');
                }
            }

            if ($action->improvement_guidance_id !== null) {
                $guidance = ImprovementGuidance::query()->find($action->improvement_guidance_id);

                if ($guidance === null || $guidance->evaluation_id !== $action->evaluation_id || $guidance->organization_id !== $action->organization_id) {
                    throw new DomainStateTransitionException('A creator action guidance must belong to the action evaluation and organization.');
                }
            }

            if ($action->improvement_opportunity_id !== null) {
                $opportunity = ImprovementOpportunity::query()->find($action->improvement_opportunity_id);

                if ($opportunity === null || $opportunity->evaluation_id !== $action->evaluation_id || $opportunity->organization_id !== $action->organization_id) {
                    throw new DomainStateTransitionException('A creator action opportunity must belong to the action evaluation and organization.');
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

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /** @return BelongsTo<ImprovementGuidance, $this> */
    public function improvementGuidance(): BelongsTo
    {
        return $this->belongsTo(ImprovementGuidance::class);
    }

    /** @return BelongsTo<ImprovementOpportunity, $this> */
    public function improvementOpportunity(): BelongsTo
    {
        return $this->belongsTo(ImprovementOpportunity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
