<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditorAssignment extends Model
{
    use HasFactory;
    protected $fillable = ['evaluation_id','auditor_id','sequence','status','assigned_at','accepted_at','completed_at','compensation_amount_minor','compensation_currency','compensation_status'];
    protected function casts(): array { return ['assigned_at'=>'datetime','accepted_at'=>'datetime','completed_at'=>'datetime']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(Evaluation::class); }
    public function auditor(): BelongsTo { return $this->belongsTo(User::class,'auditor_id'); }
    public function conflictDeclarations(): HasMany { return $this->hasMany(ConflictDeclaration::class); }
    public function evaluations(): HasMany { return $this->hasMany(AuditorEvaluation::class); }
}