<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** @return BelongsTo<Evaluation, AuditorAssignment> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<User, AuditorAssignment> */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /** @return HasMany<ConflictDeclaration, AuditorAssignment> */
    public function conflictDeclarations(): HasMany
    {
        return $this->hasMany(ConflictDeclaration::class);
    }

    /** @return HasMany<AuditorEvaluation, AuditorAssignment> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(AuditorEvaluation::class);
    }

    /** @return HasOne<AuditorCompensation, AuditorAssignment> */
    public function compensation(): HasOne
    {
        return $this->hasOne(AuditorCompensation::class);
    }
}
