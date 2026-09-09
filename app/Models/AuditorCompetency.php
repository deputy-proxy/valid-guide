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
 * @property CarbonImmutable|null $verified_at
 * @property int|null $verified_by
 * @property int $auditor_profile_id
 * @property string|null $topic
 * @property string|null $experience_type
 * @property int|null $years_experience
 */
class AuditorCompetency extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_profile_id', 'topic', 'experience_type', 'years_experience', 'evidence', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $competency): void {
            if ($competency->getOriginal('verified_at') !== null) {
                throw new DomainStateTransitionException('Verified Auditor competencies are immutable.');
            }
        });

        static::deleting(function (self $competency): void {
            if ($competency->verified_at !== null) {
                throw new DomainStateTransitionException('Verified Auditor competencies cannot be deleted.');
            }
        });
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class, 'auditor_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
