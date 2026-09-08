<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicVerificationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'validation_id',
        'public_slug',
        'snapshot',
        'directory_visible',
        'full_report_visible',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'directory_visible' => 'boolean',
            'full_report_visible' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->isDirty('validation_id') || $record->isDirty('public_slug') || $record->isDirty('published_at')) {
                throw new DomainStateTransitionException('Public verification record identity and publication provenance are immutable.');
            }
        });

        static::deleting(function (): void {
            throw new DomainStateTransitionException('Public verification records cannot be deleted.');
        });
    }

    public function validation(): BelongsTo
    {
        return $this->belongsTo(Validation::class);
    }
}
