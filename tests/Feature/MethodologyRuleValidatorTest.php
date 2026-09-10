<?php

declare(strict_types=1);

use App\Enums\MethodologyDimension;
use App\Enums\PlatformRole;
use App\Models\Criterion;
use App\Models\EvaluationStandard;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\MethodologyRuleValidator;
use App\Services\MethodologyV1;
use App\Services\StandardVersionGovernance;

function methodologyRuleFixture(array $rules = []): Criterion
{
    $standard = EvaluationStandard::create([
        'name' => 'Rules Standard',
        'slug' => 'rules-standard-'.uniqid(),
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'draft',
    ]);

    return Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'RULE-01',
        'name' => 'Rule criterion',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 10,
        'applicability_rules' => $rules,
    ]);
}

/**
 * @return array{0: StandardVersion, 1: User}
 */
function completeMethodologyFixture(): array
{
    $standard = EvaluationStandard::create([
        'name' => 'Complete Methodology Standard',
        'slug' => 'complete-methodology-standard-'.uniqid(),
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'draft',
    ]);

    $profiles = MethodologyV1::productTypeWeightProfiles();
    $productTypes = array_keys($profiles);
    $dimensions = MethodologyDimension::cases();

    foreach ($dimensions as $index => $dimension) {
        $weights = [];
        foreach ($productTypes as $productType) {
            $weights[$productType] = $profiles[$productType][$dimension->value];
        }

        $baseWeight = $weights[array_key_first($weights)];
        $overrides = $weights;
        unset($overrides[array_key_first($overrides)]);

        Criterion::create([
            'standard_version_id' => $version->id,
            'code' => sprintf('METH-%02d', $index + 1),
            'name' => sprintf('Methodology %s criterion', $dimension->value),
            'category' => $dimension->value,
            'sequence' => $index + 1,
            'weight' => $baseWeight,
            'applicability_rules' => ['weight_overrides' => $overrides],
        ]);
    }

    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    return [$version, $admin];
}

test('valid methodology rules are accepted', function () {
    $criterion = methodologyRuleFixture([
        'product_types' => ['course', 'guide'],
        'excluded_product_types' => ['ebook'],
        'weight_overrides' => ['course' => 20],
        'mandatory_product_types' => ['course'],
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))->not->toThrow(Exception::class);
});

test('criterion dimensions must be D1 through D10', function () {
    $criterion = methodologyRuleFixture();
    $criterion->category = 'D11';

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('criterion sequence must be a positive integer', function () {
    $criterion = methodologyRuleFixture();
    $criterion->sequence = 0;

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('criterion weight must be positive', function () {
    $criterion = methodologyRuleFixture();
    $criterion->weight = 0;

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('unknown applicability rule keys are rejected', function () {
    $criterion = methodologyRuleFixture(['mystery' => true]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('unsupported product types are rejected', function () {
    $criterion = methodologyRuleFixture(['product_types' => ['courseish']]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('duplicate product types are rejected', function () {
    $criterion = methodologyRuleFixture(['product_types' => ['course', 'course']]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('conflicting inclusion and exclusion rules are rejected', function () {
    $criterion = methodologyRuleFixture([
        'product_types' => ['course'],
        'excluded_product_types' => ['course'],
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('mandatory product types must be applicable when inclusion rules exist', function () {
    $criterion = methodologyRuleFixture([
        'product_types' => ['course'],
        'mandatory_product_types' => ['guide'],
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('mandatory product types cannot be excluded', function () {
    $criterion = methodologyRuleFixture([
        'excluded_product_types' => ['course'],
        'mandatory_product_types' => ['course'],
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('weight overrides must use supported product types and non-negative numeric values', function () {
    $criterion = methodologyRuleFixture(['weight_overrides' => ['course' => -1]]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('weight overrides cannot target a non-applicable product type', function () {
    $criterion = methodologyRuleFixture([
        'excluded_product_types' => ['course'],
        'weight_overrides' => ['course' => 10],
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('a complete D1 through D10 methodology and all v1 product profiles are accepted', function () {
    [$version] = completeMethodologyFixture();

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($version))
        ->not->toThrow(Exception::class);
});

test('a standard version cannot omit a required methodology dimension', function () {
    [$version] = completeMethodologyFixture();
    $version->criteria()->where('category', MethodologyDimension::D10->value)->delete();

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($version))
        ->toThrow(DomainStateTransitionException::class, 'missing required methodology dimension D10');
});

test('product type profiles must match the approved v1 weighting profiles', function () {
    [$version] = completeMethodologyFixture();
    $criterion = $version->criteria()->where('category', MethodologyDimension::D1->value)->firstOrFail();
    $criterion->weight = 11;
    $criterion->save();

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($version))
        ->toThrow(DomainStateTransitionException::class, 'course has invalid weight for D1');
});

test('product type weights must total 100', function () {
    [$version] = completeMethodologyFixture();
    $criterion = $version->criteria()->where('category', MethodologyDimension::D10->value)->firstOrFail();
    $rules = $criterion->applicability_rules;
    $rules['weight_overrides']['course'] = 8;
    $criterion->applicability_rules = $rules;
    $criterion->save();

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($version))
        ->toThrow(DomainStateTransitionException::class);
});

test('scheduling rejects an invalid methodology profile before freezing the version', function () {
    [$version, $admin] = completeMethodologyFixture();
    $criterion = $version->criteria()->where('category', MethodologyDimension::D1->value)->firstOrFail();
    $criterion->weight = 11;
    $criterion->save();
    $version->effective_at = now()->addDay();
    $version->save();

    expect(fn () => app(StandardVersionGovernance::class)->schedule($version, $admin))
        ->toThrow(DomainStateTransitionException::class);

    expect($version->fresh()->status->value)->toBe('draft');
});
