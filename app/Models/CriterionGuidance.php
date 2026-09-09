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

    public function save(array $options = []): bool
    {
        $this->assertVersionContentIsMutable();

        return parent::save($options);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $guidance): void {
            $status = $guidance->standardVersionStatus();

            if ($status !== StandardVersionStatus::Draft->value) {
                throw new DomainStateTransitionException(
                    'Criterion guidance may only be deleted while its standard version is draft.',
                );
            }
        });
    }

    private function assertVersionContentIsMutable(): void
    {
        $criterionId = $this->getRawOriginal('criterion_id') ?? $this->criterion_id;

        $criterion = Criterion::query()->whereKey($criterionId)->firstOrFail();
        $status = $this->standardVersionStatus($criterion->standard_version_id);

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

    private function standardVersionStatus(?int $standardVersionId = null): ?string
    {
        return StandardVersion::query()
            ->whereKey($standardVersionId ?? $this->criterion()->value('standard_version_id'))
            ->value('status');
    }

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    private function standardVersionStatus(): ?string
    {
        return StandardVersion::query()
            ->whereKey(Criterion::query()->whereKey($this->criterion_id)->value('standard_version_id'))
            ->value('status');
    }
}
