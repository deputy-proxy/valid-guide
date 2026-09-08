<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'current_version_id',
        'creator_visible_at',
        'public_visible_at',
    ];

    protected function casts(): array
    {
        return [
            'creator_visible_at' => 'datetime',
            'public_visible_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class);
    }
}
