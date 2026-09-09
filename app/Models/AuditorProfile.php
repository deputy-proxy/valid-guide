<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditor_id', 'status', 'methodology_literate', 'format_experience', 'bio', 'credentials', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'methodology_literate' => 'boolean',
            'format_experience' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(AuditorCompetency::class);
    }
}
