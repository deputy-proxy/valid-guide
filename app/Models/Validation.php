<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Validation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_release_id', 'evaluation_id', 'verification_identifier', 'issued_at',
        'status', 'suspended_at', 'revoked_at', 'superseded_at', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ValidationStatus::class,
            'issued_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $validation): void {
            $originalStatus = $validation->getOriginal('status');

            if ($originalStatus === ValidationStatus::Revoked->value) {
                throw new DomainStateTransitionException('A revoked validation is immutable.');
            }

            if ($originalStatus === ValidationStatus::Superseded->value) {
                throw new DomainStateTransitionException('A superseded validation is immutable.');
            }

            if ($validation->isDirty('verification_identifier')) {
                throw new DomainStateTransitionException('A validation verification identifier is immutable.');
            }

            if ($validation->isDirty('evaluation_id') || $validation->isDirty('product_release_id') || $validation->isDirty('issued_at')) {
                throw new DomainStateTransitionException('Validation provenance is immutable.');
            }
        });

        static::deleting(function (self $validation): void {
            throw new DomainStateTransitionException('Validation records cannot be deleted.');
        });
    }

    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function badge(): HasOne
    {
        return $this->hasOne(ValidationBadge::class);
    }

    public function publicVerificationRecord(): HasOne
    {
        return $this->hasOne(PublicVerificationRecord::class);
    }
}
