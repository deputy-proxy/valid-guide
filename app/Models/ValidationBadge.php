<?php

namespace App\Models;

use App\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationBadge extends Model
{
    use HasFactory;

    protected $fillable = [
        'validation_id',
        'verification_identifier',
        'status',
        'issued_at',
        'embed_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => ValidationStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    public function validation(): BelongsTo
    {
        return $this->belongsTo(Validation::class);
    }
}
