<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $due_at
 */
class AuditorAssignment extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id', 'auditor_id', 'sequence', 'status', 'assigned_at', 'due_at', 'accepted_at',
        'completed_at', 'compensation_amount_minor', 'compensation_currency', 'compensation_status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /** @return HasMany<ConflictDeclaration, $this> */
    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }

    /** @return HasMany<AuditorEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(AuditorEvaluation::class);
    }

    /** @return HasOne<AuditorCompensation, $this> */
    public function compensation(): HasOne
    {
        return $this->hasOne(AuditorCompensation::class);
    }
}
