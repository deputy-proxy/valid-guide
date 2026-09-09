<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationMaterialType;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationMaterial extends Model
{
    use HasFactory;

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
        static::updating(function (self $material): void {
            if ($material->getOriginal('verified_at') !== null) {
                $allowed = ['status', 'verification_notes'];

                if (array_diff(array_keys($material->getDirty()), $allowed) !== []) {
                    throw new DomainStateTransitionException('Verified evaluation materials are immutable.');
                }
            }
        });

        static::deleting(function (self $material): void {
            if ($material->submitted_at !== null) {
                throw new DomainStateTransitionException('Submitted evaluation materials cannot be deleted.');
            }
        });
    }

    public function evaluationRequest(): BelongsTo
    {
        return $this->belongsTo(EvaluationRequest::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
