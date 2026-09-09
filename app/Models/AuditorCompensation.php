<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditorCompensation extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditor_assignment_id', 'amount_minor', 'currency', 'status', 'payable_at',
        'forfeited_at', 'paid_at', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payable_at' => 'datetime',
            'forfeited_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $compensation): void {
            if ($compensation->getOriginal('paid_at') !== null) {
                throw new DomainStateTransitionException('Paid auditor compensation is immutable.');
            }

            if (array_intersect(array_keys($compensation->getDirty()), ['auditor_assignment_id', 'amount_minor', 'currency']) !== []) {
                throw new DomainStateTransitionException('Auditor compensation identity and amount are immutable.');
            }
        });

        static::deleting(function (self $compensation): void {
            throw new DomainStateTransitionException('Auditor compensation cannot be deleted.');
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AuditorAssignment::class, 'auditor_assignment_id');
    }
}
