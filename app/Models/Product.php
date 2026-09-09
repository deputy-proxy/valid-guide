<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ProductType $product_type
 */
class Product extends Model
{
    /** @use HasFactory<Factory> */
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

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ProductRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(ProductRelease::class);
    }

    /** @return HasMany<EvaluationRequest, $this> */
    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }
}
