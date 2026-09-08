<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'version_number',
        'content_structure',
        'abstract',
        'decision_snapshot',
        'standard_version_snapshot',
        'published_at',
        'created_by',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'content_structure' => 'array',
            'decision_snapshot' => 'array',
            'standard_version_snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
