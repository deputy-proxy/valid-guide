<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionVote extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','criterion_id','criterion_result_id','auditor_id','decision'];
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function criterion(): BelongsTo { return $this->belongsTo(Criterion::class); }
    public function criterionResult(): BelongsTo { return $this->belongsTo(CriterionResult::class); }
    public function auditor(): BelongsTo { return $this->belongsTo(User::class,'auditor_id'); }
}