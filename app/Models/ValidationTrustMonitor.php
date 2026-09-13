<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationTrustMonitorCadence;
use App\Enums\ValidationTrustMonitorStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ValidationTrustMonitorStatus $status
 * @property ValidationTrustMonitorCadence $cadence
 */
class ValidationTrustMonitor extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'validation_id',
        'cadence',
        'status',
        'last_checked_at',
        'next_check_at',
        'observed_fingerprint',
        'failure_fingerprint',
        'failure_reason',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cadence' => ValidationTrustMonitorCadence::class,
            'status' => ValidationTrustMonitorStatus::class,
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Validation, $this> */
    public function validation(): BelongsTo
    {
        return $this->belongsTo(Validation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<ValidationTrustMonitorEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ValidationTrustMonitorEvent::class);
    }
}
