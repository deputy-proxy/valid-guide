<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'auditor_evaluation_id',
        'criterion_result_id',
        'finding_id',
        'type',
        'title',
        'description',
        'source_url',
        'storage_path',
        'provenance',
        'captured_at',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<AuditorEvaluation, $this> */
    public function auditorEvaluation(): BelongsTo
    {
        return $this->belongsTo(AuditorEvaluation::class);
    }

    /** @return BelongsTo<CriterionResult, $this> */
    public function criterionResult(): BelongsTo
    {
        return $this->belongsTo(CriterionResult::class);
    }

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
