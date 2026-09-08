<?php

namespace App\Models;

use App\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_release_id', 'evaluation_id', 'verification_identifier', 'issued_at',
        'status', 'suspended_at', 'revoked_at', 'superseded_at', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ValidationStatus::class,
            'issued_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function productRelease(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
