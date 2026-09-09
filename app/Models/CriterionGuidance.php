<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StandardVersionStatus;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    public function save(array $options = []): bool
    {
        $this->assertVersionContentIsMutable();

        return parent::save($options);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $guidance): void {
            if ($guidance->standardVersionStatus() !== StandardVersionStatus::Draft->value) {
                throw new DomainStateTransitionException(
                    'Criterion guidance may only be deleted while its standard version is draft.',
                );
            }
        });
    }

    private function assertVersionContentIsMutable(): void
    {
        if (! $this->exists) {
            $criterionId = $this->criterion_id;
        } else {
            $criterionId = $this->getRawOriginal('criterion_id');
        }

        $status = $this->standardVersionStatus($criterionId);

        if (in_array($status, [
            StandardVersionStatus::Scheduled->value,
            StandardVersionStatus::Effective->value,
            StandardVersionStatus::Retired->value,
        ], true)) {
            throw new DomainStateTransitionException(
                'Criterion guidance is immutable once its standard version is scheduled.',
            );
        }
    }

    private function standardVersionStatus(?int $criterionId = null): ?string
    {
        $standardVersionId = Criterion::query()
            ->whereKey($criterionId ?? $this->criterion_id)
            ->value('standard_version_id');

        return DB::table('standard_versions')
            ->where('id', $standardVersionId)
            ->value('status');
    }

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
