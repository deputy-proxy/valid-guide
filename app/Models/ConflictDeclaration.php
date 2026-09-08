<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictDeclaration extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','auditor_assignment_id','declaration_type','disclosure','outcome','determined_by','determined_at'];
    protected function casts(): array { return ['determined_at'=>'datetime']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(AuditorAssignment::class,'auditor_assignment_id'); }
    public function determinedBy(): BelongsTo { return $this->belongsTo(User::class,'determined_by'); }
}