<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationTrustMonitorEventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationTrustMonitorEvent extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'validation_trust_monitor_id',
        'organization_id',
        'type',
        'fingerprint',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ValidationTrustMonitorEventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Validation trust monitor events are immutable.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Validation trust monitor events cannot be deleted.');
        });
    }

    /** @return BelongsTo<ValidationTrustMonitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(ValidationTrustMonitor::class, 'validation_trust_monitor_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
