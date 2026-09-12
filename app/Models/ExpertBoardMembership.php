<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpertBoardMembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ExpertBoardMembershipStatus $status
 * @property Carbon|null $applied_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $removed_at
 */
class ExpertBoardMembership extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $fillable = [
        'auditor_profile_id',
        'status',
        'applied_at',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'suspended_at',
        'removed_at',
        'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpertBoardMembershipStatus::class,
            'applied_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AuditorProfile, $this> */
    public function auditorProfile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
