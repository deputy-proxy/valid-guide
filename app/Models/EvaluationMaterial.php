<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $verified_at
 * @property int|null $verified_by
 */
class EvaluationMaterial extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    /** @var list<EvaluationRequestStatus> */
    private const CREATOR_SUBMISSION_STATUSES = [
        EvaluationRequestStatus::Paid,
        EvaluationRequestStatus::Intake,
        EvaluationRequestStatus::AwaitingCreator,
    ];

    protected $fillable = [
        'evaluation_request_id',
        'submitted_by',
        'type',
        'label',
        'description',
        'location',
        'metadata',
        'status',
        'submitted_at',
        'verified_at',
        'verified_by',
        'verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => EvaluationMaterialType::class,
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $material): void {
            if ($material->submitted_at === null || $material->submitted_by === null) {
                throw new DomainStateTransitionException('Evaluation materials must have a submitter and submission timestamp.');
            }

            $request = EvaluationRequest::query()->find($material->evaluation_request_id);
            if ($request === null || in_array($request->status, self::CREATOR_SUBMISSION_STATUSES, true) === false) {
                throw new DomainStateTransitionException('Evaluation materials can only be submitted during the paid intake workflow.');
            }
        });

        static::updating(function (): void {
            throw new DomainStateTransitionException('Submitted evaluation materials are immutable.');
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Submitted evaluation materials cannot be deleted.');
        });
    }

    /** @return BelongsTo<EvaluationRequest, $this> */
    public function evaluationRequest(): BelongsTo
    {
        return $this->belongsTo(EvaluationRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
