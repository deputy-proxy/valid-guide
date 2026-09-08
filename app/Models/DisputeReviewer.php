<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeReviewer extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_id',
        'reviewer_id',
        'assigned_by',
        'status',
        'assigned_at',
        'completed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $reviewer): void {
            if ($reviewer->getOriginal('status') === 'completed') {
                throw new DomainStateTransitionException('Completed dispute reviews are immutable.');
            }
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Dispute reviewer assignments cannot be deleted.');
        });
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
