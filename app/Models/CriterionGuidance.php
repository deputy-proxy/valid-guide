<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CriterionGuidance extends Model
{
    use HasFactory;
    protected $fillable = ['criterion_id','title','content','evidence_expectations','scoring_anchors'];
    protected function casts(): array { return ['evidence_expectations'=>'array','scoring_anchors'=>'array']; }
    public function criterion(): BelongsTo { return $this->belongsTo(Criterion::class); }
}