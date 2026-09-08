<?php

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditor_evaluation_id',
        'criterion_id',
        'assessment',
        'score',
        'rationale',
        'confidence',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'confidence' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $result): void {
            if ($result->auditorEvaluation()->whereNotNull('locked_at')->exists()) {
                throw new DomainStateTransitionException('Criterion results are immutable after auditor submission.');
            }
        });

        static::deleting(function (self $result): void {
            if ($result->auditorEvaluation()->whereNotNull('locked_at')->exists()) {
                throw new DomainStateTransitionException('Criterion results are immutable after auditor submission.');
            }
        });
    }

    public function auditorEvaluation(): BelongsTo
    {
        return $this->belongsTo(AuditorEvaluation::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
