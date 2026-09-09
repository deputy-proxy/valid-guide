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
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $determined_by
 * @property CarbonImmutable|null $determined_at
 */
class AuditorAnnualConflictDeclaration extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_id', 'year', 'disclosure', 'outcome', 'submitted_at', 'determined_by', 'determined_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'submitted_at' => 'datetime',
            'determined_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if ($declaration->getOriginal('determined_at') !== null) {
                throw new DomainStateTransitionException('A determined annual conflict declaration is immutable.');
            }
        });

        static::deleting(function (self $declaration): void {
            throw new DomainStateTransitionException('Annual conflict declarations cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function determinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'determined_by');
    }
}
