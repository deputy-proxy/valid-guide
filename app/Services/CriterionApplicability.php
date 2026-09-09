<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Criterion;
use App\Models\Product;

final class CriterionApplicability
{
    /** @return array{applicable:bool, weight:float, mandatory:bool} */
    public function resolve(Criterion $criterion, Product $product): array
    {
        $rules = $criterion->applicability_rules ?? [];
        $type = $product->product_type->value;

        $applicableTypes = $rules['product_types'] ?? null;
        if ($applicableTypes !== null) {
            if (! is_array($applicableTypes) || ! in_array($type, $applicableTypes, true)) {
                return ['applicable' => false, 'weight' => (float) $criterion->weight, 'mandatory' => false];
            }
        }

        $excludedTypes = $rules['excluded_product_types'] ?? [];
        if ($excludedTypes !== []) {
            if (! is_array($excludedTypes)) {
                throw new DomainStateTransitionException(sprintf('Criterion %s has invalid excluded product types.', $criterion->code));
            }

            if (in_array($type, $excludedTypes, true)) {
                return ['applicable' => false, 'weight' => (float) $criterion->weight, 'mandatory' => false];
            }
        }

        $weight = (float) $criterion->weight;
        $weightOverrides = $rules['weight_overrides'] ?? [];
        if ($weightOverrides !== []) {
            if (! is_array($weightOverrides)) {
                throw new DomainStateTransitionException(sprintf('Criterion %s has invalid weight overrides.', $criterion->code));
            }

            if (array_key_exists($type, $weightOverrides)) {
                $weight = (float) $weightOverrides[$type];
            }
        }

        $mandatory = (bool) $criterion->is_mandatory;
        $mandatoryTypes = $rules['mandatory_product_types'] ?? [];
        if ($mandatoryTypes !== []) {
            if (! is_array($mandatoryTypes)) {
                throw new DomainStateTransitionException(sprintf('Criterion %s has invalid mandatory product types.', $criterion->code));
            }

            $mandatory = in_array($type, $mandatoryTypes, true);
        }

        if ($weight < 0) {
            throw new DomainStateTransitionException(sprintf('Criterion %s cannot have a negative applicable weight.', $criterion->code));
        }

        return ['applicable' => true, 'weight' => $weight, 'mandatory' => $mandatory];
    }
}
