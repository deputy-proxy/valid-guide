<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EvaluationComplexity;
use App\Enums\ProductType;
use App\Models\ServicePackage;

final class EvaluationQuoteService
{
    public function quote(
        ServicePackage $package,
        ProductType $productType,
        EvaluationComplexity $complexity,
    ): EvaluationQuote {
        if ($package->status !== 'active') {
            throw new DomainStateTransitionException('Only active service packages can be quoted.');
        }

        $productTypes = $package->product_types ?? [];
        $complexityLevels = $package->complexity_levels ?? [];

        if (! $this->contains($productTypes, $productType->value)) {
            throw new DomainStateTransitionException('The selected product type is not available for this service package.');
        }

        if (! $this->contains($complexityLevels, $complexity->value)) {
            throw new DomainStateTransitionException('The selected complexity is not available for this service package.');
        }

        if ($package->price_minor <= 0) {
            throw new DomainStateTransitionException('A service package must have a positive price.');
        }

        $currency = strtoupper($package->currency);
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainStateTransitionException('A service package must use a valid three-letter currency code.');
        }

        return EvaluationQuote::fromPackage($package, $complexity);
    }

    /** @param list<string> $values */
    private function contains(array $values, string $value): bool
    {
        return in_array($value, $values, true);
    }
}
