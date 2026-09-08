<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','criterion_id','auditor_evaluation_id','type','severity','title','description','status'];
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function criterion(): BelongsTo { return $this->belongsTo(Criterion::class); }
    public function auditorEvaluation(): BelongsTo { return $this->belongsTo(AuditorEvaluation::class); }
    public function evidence(): HasMany { return $this->hasMany(Evidence::class); }
}