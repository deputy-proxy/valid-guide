<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditorEvaluation extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','auditor_assignment_id','version','status','submitted_at','locked_at'];
    protected function casts(): array { return ['submitted_at'=>'datetime','locked_at'=>'datetime']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(AuditorAssignment::class,'auditor_assignment_id'); }
    public function criterionResults(): HasMany { return $this->hasMany(CriterionResult::class); }
    public function findings(): HasMany { return $this->hasMany(Finding::class); }
    public function evidence(): HasMany { return $this->hasMany(Evidence::class); }
}