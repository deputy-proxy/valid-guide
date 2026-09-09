<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CriterionAssessment;
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
        $this->validateScoringConfiguration($version);
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

        if ((int) $criterion->sequence < 1) {
            throw new DomainStateTransitionException(sprintf('Criterion %s must have a positive integer sequence.', $criterion->code));
        }

        if ((float) $criterion->weight <= 0) {
            throw new DomainStateTransitionException(sprintf('Criterion %s must have a positive weight.', $criterion->code));
        }

        $rules = $criterion->applicability_rules ?? [];
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
        if ($overrides !== []) {
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
    }

    private function validateScoringConfiguration(StandardVersion $version): void
    {
        $anchors = $version->score_anchors;
        if (! is_array($anchors)) {
            throw new DomainStateTransitionException('A standard version must define score anchors before it can be scheduled.');
        }

        $expected = array_map(static fn (CriterionAssessment $assessment): string => $assessment->value, CriterionAssessment::cases());
        if (array_keys($anchors) !== $expected) {
            throw new DomainStateTransitionException('A standard version must define exactly one score anchor for every allowed assessment.');
        }

        $ranges = [];
        foreach (CriterionAssessment::cases() as $assessment) {
            $anchor = $anchors[$assessment->value];
            if (! is_array($anchor) || ! array_key_exists('min', $anchor) || ! array_key_exists('max', $anchor)) {
                throw new DomainStateTransitionException(sprintf('Score anchor for %s must define min and max.', $assessment->value));
            }

            if (! $assessment->isScored()) {
                if ($anchor['min'] !== null || $anchor['max'] !== null) {
                    throw new DomainStateTransitionException(sprintf('Assessment %s must not define a numerical score range.', $assessment->value));
                }

                continue;
            }

            if (! is_int($anchor['min']) || ! is_int($anchor['max']) || $anchor['min'] < 0 || $anchor['max'] > 100 || $anchor['min'] > $anchor['max']) {
                throw new DomainStateTransitionException(sprintf('Score anchor for %s must be an ordered integer range within 0-100.', $assessment->value));
            }

            $ranges[] = ['min' => $anchor['min'], 'max' => $anchor['max'], 'assessment' => $assessment->value];
        }

        usort($ranges, static fn (array $a, array $b): int => $a['min'] <=> $b['min']);
        $expectedMinimum = 0;
        foreach ($ranges as $range) {
            if ($range['min'] !== $expectedMinimum) {
                throw new DomainStateTransitionException(sprintf('Score anchors must cover 0-100 continuously; %s starts at %d instead of %d.', $range['assessment'], $range['min'], $expectedMinimum));
            }

            $expectedMinimum = $range['max'] + 1;
        }

        if ($expectedMinimum !== 101) {
            throw new DomainStateTransitionException('Score anchors must cover the complete 0-100 numerical range.');
        }

        $thresholds = $version->decision_thresholds;
        $expectedThresholds = ['overall_minimum', 'mandatory_minimum', 'dimension_minimum'];
        if (! is_array($thresholds) || array_keys($thresholds) !== $expectedThresholds) {
            throw new DomainStateTransitionException('A standard version must define overall, mandatory and dimension decision thresholds.');
        }

        foreach ($expectedThresholds as $key) {
            $threshold = $thresholds[$key];
            if (! is_int($threshold) && ! is_float($threshold)) {
                throw new DomainStateTransitionException(sprintf('Decision threshold %s must be numeric.', $key));
            }

            if ($threshold < 0 || $threshold > 100) {
                throw new DomainStateTransitionException(sprintf('Decision threshold %s must be between 0 and 100.', $key));
            }
        }
    }

    /** @param array<string, mixed> $rules */
    /**
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
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
