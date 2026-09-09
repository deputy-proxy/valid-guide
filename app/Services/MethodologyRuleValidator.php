<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MethodologyDimension;
use App\Enums\ProductType;
use App\Models\Criterion;
use App\Models\StandardVersion;

final class MethodologyRuleValidator
{
    private const APPLICABILITY_KEYS = [
        'product_types',
        'excluded_product_types',
        'weight_overrides',
        'mandatory_product_types',
    ];

    public function validateStandardVersion(StandardVersion $version): void
    {
        $criteria = $version->criteria()->get();

        if ($criteria->isEmpty()) {
            throw new DomainStateTransitionException('A standard version must contain at least one criterion before it can be scheduled.');
        }

        $codes = $criteria->pluck('code');
        if ($codes->count() !== $codes->unique()->count()) {
            throw new DomainStateTransitionException('Criterion codes must be unique within a standard version.');
        }

        $sequences = $criteria->pluck('sequence')->filter(fn ($sequence) => $sequence !== null);
        if ($sequences->count() !== $criteria->count()) {
            throw new DomainStateTransitionException('Every criterion must have a sequence before a standard version can be scheduled.');
        }

        if ($sequences->count() !== $sequences->unique()->count()) {
            throw new DomainStateTransitionException('Criterion sequences must be unique within a standard version.');
        }

        foreach ($criteria as $criterion) {
            $this->validateCriterion($criterion);
        }
    }

    public function validateCriterion(Criterion $criterion): void
    {
        if (blank($criterion->code) || blank($criterion->name)) {
            throw new DomainStateTransitionException(sprintf('Criterion %s must have a code and name.', $criterion->code ?: '(unknown)'));
        }

        if (! is_string($criterion->category) || MethodologyDimension::tryFrom($criterion->category) === null) {
            throw new DomainStateTransitionException(sprintf(
                'Criterion %s must have a valid methodology dimension (D1-D10).',
                $criterion->code,
            ));
        }

        if (! is_int($criterion->sequence) && ! (is_string($criterion->sequence) && ctype_digit($criterion->sequence))) {
            throw new DomainStateTransitionException(sprintf('Criterion %s must have a positive integer sequence.', $criterion->code));
        }

        if ((int) $criterion->sequence < 1) {
            throw new DomainStateTransitionException(sprintf('Criterion %s must have a positive integer sequence.', $criterion->code));
        }

        if ((float) $criterion->weight <= 0) {
            throw new DomainStateTransitionException(sprintf('Criterion %s must have a positive weight.', $criterion->code));
        }

        $rules = $criterion->applicability_rules ?? [];
        if (! is_array($rules)) {
            throw new DomainStateTransitionException(sprintf('Criterion %s has invalid applicability rules.', $criterion->code));
        }

        $unknownKeys = array_diff(array_keys($rules), self::APPLICABILITY_KEYS);
        if ($unknownKeys !== []) {
            throw new DomainStateTransitionException(sprintf(
                'Criterion %s has unsupported applicability rule keys: %s.',
                $criterion->code,
                implode(', ', $unknownKeys),
            ));
        }

        $productTypes = $this->productTypes($rules, 'product_types', $criterion);
        $excludedTypes = $this->productTypes($rules, 'excluded_product_types', $criterion);
        $mandatoryTypes = $this->productTypes($rules, 'mandatory_product_types', $criterion);

        $overlap = array_values(array_intersect($productTypes, $excludedTypes));
        if ($overlap !== []) {
            throw new DomainStateTransitionException(sprintf(
                'Criterion %s cannot both include and exclude product type(s): %s.',
                $criterion->code,
                implode(', ', $overlap),
            ));
        }

        if ($productTypes !== []) {
            $invalidMandatory = array_values(array_diff($mandatoryTypes, $productTypes));
            if ($invalidMandatory !== []) {
                throw new DomainStateTransitionException(sprintf(
                    'Criterion %s has mandatory product types outside its applicable product types: %s.',
                    $criterion->code,
                    implode(', ', $invalidMandatory),
                ));
            }
        }

        $overrides = $rules['weight_overrides'] ?? [];
        if (! is_array($overrides) || array_is_list($overrides)) {
            throw new DomainStateTransitionException(sprintf('Criterion %s has invalid weight overrides.', $criterion->code));
        }

        foreach ($overrides as $type => $weight) {
            $this->assertProductType($type, $criterion, 'weight override');
            if (! is_int($weight) && ! is_float($weight) && ! (is_string($weight) && is_numeric($weight))) {
                throw new DomainStateTransitionException(sprintf('Criterion %s has a non-numeric weight override for %s.', $criterion->code, $type));
            }

            if ((float) $weight < 0) {
                throw new DomainStateTransitionException(sprintf('Criterion %s cannot have a negative weight override for %s.', $criterion->code, $type));
            }
        }
    }

    /** @return list<string> */
    private function productTypes(array $rules, string $key, Criterion $criterion): array
    {
        $values = $rules[$key] ?? [];
        if (! is_array($values) || ! array_is_list($values)) {
            throw new DomainStateTransitionException(sprintf('Criterion %s has invalid %s.', $criterion->code, $key));
        }

        $normalized = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new DomainStateTransitionException(sprintf('Criterion %s has a non-string product type in %s.', $criterion->code, $key));
            }

            $this->assertProductType($value, $criterion, $key);
            $normalized[] = $value;
        }

        if (count($normalized) !== count(array_unique($normalized))) {
            throw new DomainStateTransitionException(sprintf('Criterion %s contains duplicate product types in %s.', $criterion->code, $key));
        }

        return $normalized;
    }

    private function assertProductType(string $value, Criterion $criterion, string $context): void
    {
        if (ProductType::tryFrom($value) === null) {
            throw new DomainStateTransitionException(sprintf(
                'Criterion %s references unsupported product type %s in %s.',
                $criterion->code,
                $value,
                $context,
            ));
        }
    }
}
