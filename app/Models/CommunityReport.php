<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityReportReason;
use App\Enums\CommunityReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CommunityReportStatus $status */
class CommunityReport extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'community_contribution_id',
        'reporter_id',
        'reason',
        'details',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => CommunityReportReason::class,
            'status' => CommunityReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CommunityContribution, $this> */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(CommunityContribution::class, 'community_contribution_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
