<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'title', 'slug', 'product_type', 'subject_area', 'description', 'url',
        'reference_price', 'reference_currency', 'target_audience', 'claimed_outcomes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'reference_price' => 'decimal:2',
            'claimed_outcomes' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ProductRelease::class);
    }

    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }
}
