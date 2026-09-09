<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClarificationRequestStatus;
use App\Enums\ClarificationRequestType;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ClarificationRequestStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $resolved_by
 */
class ClarificationRequest extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id', 'organization_id', 'submitted_by', 'type', 'message', 'response',
        'status', 'submitted_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClarificationRequestType::class,
            'status' => ClarificationRequestStatus::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            $status = $request->getRawOriginal('status');
            if ($status === ClarificationRequestStatus::Closed->value) {
                throw new DomainStateTransitionException('Closed clarification requests are immutable.');
            }
            $immutableContent = ['evaluation_id', 'organization_id', 'submitted_by', 'type', 'message'];
            if ($request->getRawOriginal('submitted_at') !== null && array_intersect(array_keys($request->getDirty()), $immutableContent) !== []) {
                throw new DomainStateTransitionException('Submitted clarification content is immutable.');
            }
            if ($status === ClarificationRequestStatus::Answered->value && array_diff(array_keys($request->getDirty()), ['status', 'resolved_at', 'resolved_by', 'response'])) {
                throw new DomainStateTransitionException('Answered clarification content is immutable.');
            }
        });
        static::deleting(function (self $request): void {
            if ($request->submitted_at !== null) {
                throw new DomainStateTransitionException('Submitted clarification requests cannot be deleted.');
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
}
