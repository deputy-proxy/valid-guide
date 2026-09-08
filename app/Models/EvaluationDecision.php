<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'decision',
        'rationale',
        'decided_by',
        'decided_at',
        'supersedes_decision_id',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainStateTransitionException('Evaluation decisions are immutable once recorded.');
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Evaluation decisions cannot be deleted once recorded.');
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_decision_id');
    }
}
