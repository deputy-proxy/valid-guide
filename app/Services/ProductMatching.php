<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductReleaseStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductMatching
{
    /** @return Collection<int, ProductMatch> */
    public function match(
        ?ProductAudience $audience = null,
        ?ProductGoal $goal = null,
        ?ProductType $productType = null,
        ?string $subjectArea = null,
        ?string $language = null,
    ): Collection {
        $products = Product::query()
            ->where('status', ProductStatus::Active->value)
            ->whereHas('releases', function ($query): void {
                $query->where('status', ProductReleaseStatus::Current->value)
                    ->whereHas('validations', function ($query): void {
                        $query->where('status', ValidationStatus::Active->value);
                    });
            })
            ->with(['releases' => function ($query): void {
                $query->where('status', ProductReleaseStatus::Current->value)
                    ->with(['validations' => function ($query): void {
                        $query->where('status', ValidationStatus::Active->value);
                    }]);
            }])
            ->get();

        return $products
            ->map(function (Product $product) use ($audience, $goal, $productType, $subjectArea, $language): ?ProductMatch {
                $release = $product->releases->first();
                $validation = $release?->validations->first();

                if ($release === null || $validation === null || $validation->status !== ValidationStatus::Active) {
                    return null;
                }

                $reasons = [];

                if ($audience !== null) {
                    if (! self::contains($product->getRawOriginal('matching_audiences'), $audience->value)) {
                        return null;
                    }

                    $reasons[] = sprintf('Audience matches: %s.', self::label($audience->value));
                }

                if ($goal !== null) {
                    if (! self::contains($product->getRawOriginal('matching_goals'), $goal->value)) {
                        return null;
                    }

                    $reasons[] = sprintf('Use case matches: %s.', self::label($goal->value));
                }

                if ($productType !== null) {
                    if ($product->product_type !== $productType) {
                        return null;
                    }

                    $reasons[] = sprintf('Product type matches: %s.', self::label($productType->value));
                }

                if ($subjectArea !== null) {
                    if (self::normalize($product->subject_area) !== self::normalize($subjectArea)) {
                        return null;
                    }

                    $reasons[] = sprintf('Subject area matches: %s.', $product->subject_area);
                }

                if ($language !== null) {
                    if (self::normalize($product->language) !== self::normalize($language)) {
                        return null;
                    }

                    $reasons[] = sprintf('Language matches: %s.', $product->language);
                }

                return new ProductMatch(
                    productId: (int) $product->getKey(),
                    title: (string) $product->title,
                    slug: (string) $product->slug,
                    productType: $product->product_type,
                    subjectArea: $product->subject_area,
                    language: $product->language,
                    releaseIdentifier: (string) $release->release_identifier,
                    validationStatus: $validation->status,
                    validationEvidence: sprintf('Active validation for current release %s.', $release->release_identifier),
                    suitabilityReasons: $reasons,
                );
            })
            ->filter(fn ($match): bool => $match instanceof ProductMatch)
            ->values();
    }

    private static function contains(mixed $json, string $value): bool
    {
        if (! is_string($json)) {
            return false;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && in_array($value, $decoded, true);
    }

    private static function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private static function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
