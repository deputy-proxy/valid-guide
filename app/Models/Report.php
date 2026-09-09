<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'current_version_id',
        'creator_visible_at',
        'public_visible_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'creator_visible_at' => 'datetime',
            'public_visible_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            if ($report->delivered_at !== null) {
                throw new DomainStateTransitionException('A report must be delivered through ReportDelivery.');
            }
        });

        static::updating(function (self $report): void {
            if ($report->isDirty('evaluation_id')) {
                throw new DomainStateTransitionException('Report provenance is immutable after creation.');
            }

            if ($report->isDirty('current_version_id') || $report->isDirty('creator_visible_at') || $report->isDirty('public_visible_at')) {
                throw new DomainStateTransitionException('Report lifecycle fields can only be changed through report services.');
            }

            if ($report->isDirty('delivered_at')) {
                throw new DomainStateTransitionException('Report delivery can only be recorded through ReportDelivery.');
            }
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Reports cannot be deleted; their version history is part of the evaluation record.');
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class);
    }
}
