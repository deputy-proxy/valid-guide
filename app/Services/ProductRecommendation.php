<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductType;
use App\Enums\ValidationStatus;

final readonly class ProductRecommendation
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ProductType $productType,
        public ?string $subjectArea,
        public ?string $language,
        public string $verificationIdentifier,
        public string $releaseIdentifier,
        public ValidationStatus $validationStatus,
        public int $score,
        public array $reasons,
    ) {}
}
