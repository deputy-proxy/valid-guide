<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $determined_at
 */
class ConflictDeclaration extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id', 'auditor_assignment_id', 'declaration_type', 'disclosure', 'outcome', 'determined_by', 'determined_at',
    ];

    protected function casts(): array
    {
        return ['determined_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if ($declaration->getRawOriginal('determined_at') !== null) {
                throw new DomainStateTransitionException('A determined conflict declaration is immutable.');
            }
        });
        static::deleting(function (self $declaration): void {
            if ($declaration->determined_at !== null) {
                throw new DomainStateTransitionException('A determined conflict declaration is immutable.');
            }
        });
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<AuditorAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AuditorAssignment::class, 'auditor_assignment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function determinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'determined_by');
    }
}
