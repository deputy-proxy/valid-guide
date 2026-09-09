<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StandardVersionStatus;
use App\Services\DomainStateTransitionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property StandardVersionStatus $status
 * @property CarbonImmutable|null $effective_at
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable|null $approved_at
 * @property int|null $approved_by
 */
class StandardVersion extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_standard_id', 'version', 'description', 'effective_at', 'retired_at',
        'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StandardVersionStatus::class,
            'effective_at' => 'datetime',
            'retired_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $status = $version->getRawOriginal('status');
            if (in_array($status, [
                StandardVersionStatus::Scheduled->value,
                StandardVersionStatus::Effective->value,
                StandardVersionStatus::Retired->value,
            ], true)) {
                $lifecycleFields = ['status', 'effective_at', 'retired_at', 'approved_by', 'approved_at'];
                if (array_diff(array_keys($version->getDirty()), $lifecycleFields)) {
                    throw new DomainStateTransitionException('Scheduled, effective and retired standard version content is immutable.');
                }
            }
        });
        static::deleting(function (self $version): void {
            if ($version->status !== StandardVersionStatus::Draft) {
                throw new DomainStateTransitionException('Only draft standard versions may be deleted.');
            }
        });
    }

    /** @return BelongsTo<EvaluationStandard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(EvaluationStandard::class, 'evaluation_standard_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /** @return HasMany<Criterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class);
    }
}
