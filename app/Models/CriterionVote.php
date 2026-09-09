<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionVote extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'criterion_id',
        'criterion_result_id',
        'auditor_id',
        'decision',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainStateTransitionException('Criterion votes are immutable once recorded.');
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Criterion votes cannot be deleted once recorded.');
        });
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    /** @return BelongsTo<CriterionResult, $this> */
    public function criterionResult(): BelongsTo
    {
        return $this->belongsTo(CriterionResult::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
