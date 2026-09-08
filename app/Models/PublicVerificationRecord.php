<?php

declare(strict_types=1);

namespace App\Models;

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

    public function validation(): BelongsTo
    {
        return $this->belongsTo(Validation::class);
    }
}
