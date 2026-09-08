<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','auditor_evaluation_id','criterion_result_id','finding_id','type','title','description','source_url','storage_path','provenance','captured_at','visibility'];
    protected function casts(): array { return ['captured_at'=>'datetime']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function auditorEvaluation(): BelongsTo { return $this->belongsTo(AuditorEvaluation::class); }
    public function criterionResult(): BelongsTo { return $this->belongsTo(CriterionResult::class); }
    public function finding(): BelongsTo { return $this->belongsTo(Finding::class); }
}