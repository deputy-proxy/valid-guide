<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ProductStatus $status
 * @property ProductType $product_type
 * @property array<int|string, mixed>|null $claimed_outcomes
 */
class Product extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'product_type',
        'subject_area',
        'description',
        'url',
        'canonical_url',
        'reference_price',
        'reference_currency',
        'target_audience',
        'claimed_outcomes',
        'language',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'reference_price' => 'decimal:2',
            'claimed_outcomes' => 'array',
            'status' => ProductStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if ($product->status !== null && $product->status !== ProductStatus::Active) {
                throw new DomainStateTransitionException('Products must be created as active.');
            }
        });

        static::updating(function (self $product): void {
            if ($product->isDirty('organization_id')) {
                throw new DomainStateTransitionException('A product cannot be moved between organizations.');
            }

            if ($product->isDirty('status')) {
                throw new DomainStateTransitionException('Product lifecycle changes must use ProductManagement.');
            }

            if ($product->getRawOriginal('status') === ProductStatus::Archived->value) {
                throw new DomainStateTransitionException('Archived products cannot be edited.');
            }
        });

        static::deleting(function (self $product): void {
            if ($product->releases()->exists() || $product->evaluationRequests()->exists()) {
                throw new DomainStateTransitionException('Products with historical release or evaluation records cannot be deleted. Archive the product instead.');
            }
        });
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
