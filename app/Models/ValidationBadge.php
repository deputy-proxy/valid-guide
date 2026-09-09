<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationBadge extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'validation_id',
        'verification_identifier',
        'status',
        'issued_at',
        'embed_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => ValidationStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $badge): void {
            if ($badge->isDirty('validation_id') || $badge->isDirty('verification_identifier') || $badge->isDirty('issued_at') || $badge->isDirty('embed_version')) {
                throw new DomainStateTransitionException('Validation badge identity and provenance are immutable.');
            }

            if ($badge->isDirty('status')) {
                throw new DomainStateTransitionException('Validation badge status can only be changed through ValidationStateTransition.');
            }
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Validation badges cannot be deleted.');
        });
    }

    /** @return BelongsTo<Validation, $this> */
    public function validation(): BelongsTo
    {
        return $this->belongsTo(Validation::class);
    }
}
