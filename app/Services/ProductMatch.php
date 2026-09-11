<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductType;
use App\Enums\ValidationStatus;

/** @property list<string> $suitabilityReasons */
final readonly class ProductMatch
{
    public function __construct(
        public int $productId,
        public string $title,
        public string $slug,
        public ProductType $productType,
        public ?string $subjectArea,
        public ?string $language,
        public string $releaseIdentifier,
        public ValidationStatus $validationStatus,
        public string $validationEvidence,
        public array $suitabilityReasons,
    ) {}
}
