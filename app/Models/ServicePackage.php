<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationComplexity;
use App\Enums\ProductType;
use App\Services\DomainStateTransitionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<string>|null $complexity_levels
 * @property list<string>|null $product_types
 * @property int $price_minor
 * @property string $currency
 */
class ServicePackage extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'product_types',
        'complexity_levels',
        'price_minor',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'product_types' => 'array',
            'complexity_levels' => 'array',
            'price_minor' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $package): void {
            $productTypes = $package->product_types ?? [];
            $complexityLevels = $package->complexity_levels ?? [];

            if ($package->price_minor <= 0) {
                throw new DomainStateTransitionException('A service package must have a positive price.');
            }

            if ($package->currency !== strtoupper($package->currency) || ! preg_match('/^[A-Z]{3}$/', $package->currency)) {
                throw new DomainStateTransitionException('A service package must use a valid three-letter currency code.');
            }

            foreach ($productTypes as $productType) {
                if (ProductType::tryFrom($productType) === null) {
                    throw new DomainStateTransitionException('Service package product types must use the controlled product taxonomy.');
                }
            }

            foreach ($complexityLevels as $complexityLevel) {
                if (EvaluationComplexity::tryFrom($complexityLevel) === null) {
                    throw new DomainStateTransitionException('Service package complexity levels must use the controlled complexity taxonomy.');
                }
            }
        });
    }

    /** @return HasMany<EvaluationRequest, $this> */
    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }
}
