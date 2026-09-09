<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditorProfileReview extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'auditor_profile_id', 'reviewed_by', 'action', 'reason', 'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainStateTransitionException('Auditor profile review records are immutable.');
        });

        static::deleting(function (): never {
            throw new DomainStateTransitionException('Auditor profile review records cannot be deleted.');
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class, 'auditor_profile_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
