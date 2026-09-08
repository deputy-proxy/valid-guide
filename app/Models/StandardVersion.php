<?php

namespace App\Models;

use App\Enums\StandardVersionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_standard_id', 'version', 'description', 'effective_at', 'retired_at',
        'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StandardVersionStatus::class,
            'effective_at' => 'datetime',
            'retired_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(EvaluationStandard::class, 'evaluation_standard_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
