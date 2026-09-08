<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationStandard extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    public function versions(): HasMany
    {
        return $this->hasMany(StandardVersion::class);
    }
}
