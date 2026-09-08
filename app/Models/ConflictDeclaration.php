<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'auditor_assignment_id',
        'declaration_type',
        'disclosure',
        'outcome',
        'determined_by',
        'determined_at',
    ];

    protected function casts(): array
    {
        return [
            'determined_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if ($declaration->getOriginal('determined_at') !== null) {
                throw new DomainStateTransitionException('A determined conflict declaration is immutable.');
            }
        });

        static::deleting(function (self $declaration): void {
            if ($declaration->determined_at !== null) {
                throw new DomainStateTransitionException('A determined conflict declaration is immutable.');
            }
        });
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AuditorAssignment::class, 'auditor_assignment_id');
    }

    public function determinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'determined_by');
    }
}
