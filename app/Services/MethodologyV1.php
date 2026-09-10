<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MethodologyDimension;
use App\Enums\ProductType;

final class MethodologyV1
{
    /**
     * @return array<string, array<string, int>>
     */
    public static function productTypeWeightProfiles(): array
    {
        return [
            ProductType::Course->value => self::profile([10, 12, 14, 10, 16, 12, 8, 6, 5, 7]),
            ProductType::CohortCourse->value => self::profile([10, 12, 12, 8, 18, 12, 8, 8, 4, 8]),
            ProductType::Guide->value => self::profile([12, 14, 16, 12, 8, 12, 10, 6, 5, 5]),
            ProductType::Ebook->value => self::profile([12, 14, 16, 12, 8, 12, 10, 6, 4, 6]),
            ProductType::Workshop->value => self::profile([10, 12, 10, 8, 18, 12, 8, 6, 5, 11]),
            ProductType::Program->value => self::profile([10, 12, 12, 10, 14, 12, 8, 6, 5, 11]),
            ProductType::Membership->value => self::profile([10, 12, 12, 8, 10, 10, 8, 8, 5, 17]),
        ];
    }

    /**
     * @param  list<int>  $weights
     * @return array<string, int>
     */
    private static function profile(array $weights): array
    {
        $dimensions = array_map(
            static fn (MethodologyDimension $dimension): string => $dimension->value,
            MethodologyDimension::cases(),
        );

        return array_combine($dimensions, $weights) ?: [];
    }
}
