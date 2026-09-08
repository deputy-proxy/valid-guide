<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criterion extends Model
{
    use HasFactory;
    protected $fillable = ['standard_version_id','code','name','description','category','sequence','weight','is_mandatory','applicability_rules','scoring_rules'];
    protected function casts(): array { return ['weight'=>'decimal:2','is_mandatory'=>'boolean','applicability_rules'=>'array','scoring_rules'=>'array']; }
    public function standardVersion(): BelongsTo { return $this->belongsTo(StandardVersion::class); }
    public function results(): HasMany { return $this->hasMany(CriterionResult::class); }
    public function guidance(): HasMany { return $this->hasMany(CriterionGuidance::class); }
}