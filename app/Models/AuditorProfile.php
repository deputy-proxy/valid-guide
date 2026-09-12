<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditorProfileStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property list<string>|null $format_experience
 * @property AuditorProfileStatus $status
 */
class AuditorProfile extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_id', 'status', 'methodology_literate', 'format_experience', 'bio', 'credentials', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AuditorProfileStatus::class,
            'methodology_literate' => 'boolean',
            'format_experience' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<AuditorCompetency, $this> */
    public function competencies(): HasMany
    {
        return $this->hasMany(AuditorCompetency::class);
    }

    /** @return HasMany<AuditorProfileReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(AuditorProfileReview::class);
    }

    /** @return HasOne<ExpertBoardMembership, $this> */
    public function expertBoardMembership(): HasOne
    {
        return $this->hasOne(ExpertBoardMembership::class);
    }

    /** @return HasOne<ExpertPublicProfile, $this> */
    public function expertPublicProfile(): HasOne
    {
        return $this->hasOne(ExpertPublicProfile::class);
    }
}
