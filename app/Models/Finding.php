<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'criterion_id',
        'auditor_evaluation_id',
        'type',
        'severity',
        'title',
        'description',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $finding): void {
            if ($finding->auditor_evaluation_id === null) {
                return;
            }

            $auditorEvaluation = AuditorEvaluation::query()->find($finding->auditor_evaluation_id);
            if ($auditorEvaluation?->locked_at !== null) {
                throw new DomainStateTransitionException('Findings belonging to a submitted Auditor evaluation are immutable.');
            }
        });

        static::deleting(function (self $finding): void {
            $auditorEvaluation = $finding->auditorEvaluation()->first();
            if ($auditorEvaluation?->locked_at !== null) {
                throw new DomainStateTransitionException('Findings belonging to a submitted Auditor evaluation are immutable.');
            }
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

    /** @return BelongsTo<AuditorEvaluation, $this> */
    public function auditorEvaluation(): BelongsTo
    {
        return $this->belongsTo(AuditorEvaluation::class);
    }

    /** @return HasMany<Evidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
