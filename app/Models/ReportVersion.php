<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'version_number',
        'content_structure',
        'abstract',
        'decision_snapshot',
        'standard_version_snapshot',
        'published_at',
        'created_by',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'content_structure' => 'array',
            'decision_snapshot' => 'array',
            'standard_version_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            throw new DomainStateTransitionException('Report versions are immutable after creation.');
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Report versions cannot be deleted.');
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
