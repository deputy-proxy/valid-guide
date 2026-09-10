<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationComplexity;
use App\Models\ServicePackage;

final readonly class EvaluationQuote
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
        public int $servicePackageId,
        public string $servicePackageName,
        public string $servicePackageDescription,
        /** @var list<string> */
        public array $productTypes,
        /** @var list<string> */
        public array $complexityLevels,
        public EvaluationComplexity $complexity,
    ) {}

    public static function fromPackage(
        ServicePackage $package,
        EvaluationComplexity $complexity,
    ): self {
        return new self(
            amountMinor: $package->price_minor,
            currency: strtoupper($package->currency),
            servicePackageId: (int) $package->getKey(),
            servicePackageName: $package->name,
            servicePackageDescription: $package->description ?? '',
            productTypes: $package->product_types ?? [],
            complexityLevels: $package->complexity_levels ?? [],
            complexity: $complexity,
        );
    }
}
