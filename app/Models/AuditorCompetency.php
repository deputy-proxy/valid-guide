<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditorCompetency extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditor_profile_id', 'topic', 'experience_type', 'years_experience', 'evidence', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AuditorProfile::class, 'auditor_profile_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
