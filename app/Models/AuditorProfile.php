<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditorProfileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditorProfile extends Model
{
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

    protected static function booted(): void
    {
        static::updating(function (self $profile): void {
            if ($profile->getOriginal('approved_at') !== null) {
                $immutable = ['approved_by', 'approved_at'];

                if (array_intersect(array_keys($profile->getDirty()), $immutable) !== []) {
                    // Re-approval is a new governance event. The current approval fields are
                    // intentionally refreshed by AuditorProfileGovernance, while the complete
                    // historical provenance lives in immutable review records and the audit log.
                    return;
                }
            }
        });
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(AuditorCompetency::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AuditorProfileReview::class);
    }
}
