<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityContributionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CommunityContributionStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $hidden_at
 * @property CarbonImmutable|null $removed_at
 */
class CommunityContribution extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_profile_id',
        'slug',
        'title',
        'body',
        'status',
        'moderation_reason',
        'moderated_by',
        'moderated_at',
        'published_at',
        'hidden_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityContributionStatus::class,
            'moderated_at' => 'datetime',
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $contribution): void {
            if ($contribution->isDirty('auditor_profile_id')) {
                throw new \LogicException('Contribution authorship is immutable.');
            }

            if ($contribution->getRawOriginal('status') === CommunityContributionStatus::Published->value && $contribution->isDirty(['title', 'body', 'slug'])) {
                throw new \LogicException('Published community contributions are immutable.');
            }
        });
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function auditorProfile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return HasMany<CommunityReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class);
    }
}
