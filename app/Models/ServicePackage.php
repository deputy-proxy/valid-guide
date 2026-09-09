<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'product_types', 'complexity_levels',
        'price', 'currency', 'status',
    ];

    protected function casts(): array
    {
        return [
            'product_types' => 'array',
            'complexity_levels' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }
}
