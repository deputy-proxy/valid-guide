<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditorAnnualConflictDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditor_id', 'year', 'disclosure', 'outcome', 'submitted_at', 'determined_by', 'determined_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'submitted_at' => 'datetime',
            'determined_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if ($declaration->getOriginal('determined_at') !== null) {
                throw new DomainStateTransitionException('A determined annual conflict declaration is immutable.');
            }
        });

        static::deleting(function (self $declaration): void {
            throw new DomainStateTransitionException('Annual conflict declarations cannot be deleted.');
        });
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function determinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'determined_by');
    }
}
