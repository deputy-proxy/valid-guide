<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','organization_id','type','grounds','status','submitted_at','resolved_at','outcome','decision_rationale'];
    protected function casts(): array { return ['submitted_at'=>'datetime','resolved_at'=>'datetime']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}