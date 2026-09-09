<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StandardVersionStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionGuidance extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'criterion_id',
        'title',
        'content',
        'evidence_expectations',
        'scoring_anchors',
    ];

    protected function casts(): array
    {
        return [
            'evidence_expectations' => 'array',
            'scoring_anchors' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $guidance): void {
            $status = $guidance->criterion()->firstOrFail()->standardVersion()->value('status');

            if (in_array($status, [
                StandardVersionStatus::Scheduled->value,
                StandardVersionStatus::Effective->value,
                StandardVersionStatus::Retired->value,
            ], true)) {
                throw new DomainStateTransitionException(
                    'Criterion guidance is immutable once its standard version is scheduled.',
                );
            }
        });

        static::deleting(function (self $guidance): void {
            $status = $guidance->criterion()->firstOrFail()->standardVersion()->value('status');

            if ($status !== StandardVersionStatus::Draft->value) {
                throw new DomainStateTransitionException(
                    'Criterion guidance may only be deleted while its standard version is draft.',
                );
            }
        });
    }

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
