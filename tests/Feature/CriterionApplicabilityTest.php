<?php

declare(strict_types=1);

use App\Models\Criterion;
use App\Models\EvaluationStandard;
use App\Models\Product;
use App\Models\StandardVersion;
use App\Services\CriterionApplicability;
use App\Services\DomainStateTransitionException;
use Illuminate\Support\Facades\DB;

function applicabilityFixture(array $rules = [], bool $mandatory = false): array
{
    $organizationId = DB::table('organizations')->insertGetId([
        'name' => 'Applicability Test', 'slug' => 'applicability-'.uniqid(), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $product = Product::create([
        'organization_id' => $organizationId, 'title' => 'Applicability Product',
        'slug' => 'applicability-'.uniqid(), 'product_type' => 'course',
    ]);
    $standard = EvaluationStandard::create(['name' => 'Applicability Standard', 'slug' => 'applicability-standard-'.uniqid()]);
    $version = StandardVersion::create(['evaluation_standard_id' => $standard->id, 'version' => '1.0', 'status' => 'draft']);
    $criterion = Criterion::create([
        'standard_version_id' => $version->id, 'code' => 'APP-01', 'name' => 'Applicability Criterion',
        'category' => 'D1', 'sequence' => 1, 'weight' => 10, 'is_mandatory' => $mandatory,
        'applicability_rules' => $rules,
    ]);

    return [$criterion, $product];
}

test('criterion is applicable by default with its base weight and mandatory state', function () {
    [$criterion, $product] = applicabilityFixture([], true);
    expect(app(CriterionApplicability::class)->resolve($criterion, $product))
        ->toMatchArray(['applicable' => true, 'weight' => 10.0, 'mandatory' => true]);
});

test('criterion can be limited to product types', function () {
    [$criterion, $product] = applicabilityFixture(['product_types' => ['guide']]);
    expect(app(CriterionApplicability::class)->resolve($criterion, $product)['applicable'])->toBeFalse();
});

test('criterion can exclude product types', function () {
    [$criterion, $product] = applicabilityFixture(['excluded_product_types' => ['course']]);
    expect(app(CriterionApplicability::class)->resolve($criterion, $product)['applicable'])->toBeFalse();
});

test('criterion can override weight and mandatory status for a product type', function () {
    [$criterion, $product] = applicabilityFixture([
        'weight_overrides' => ['course' => 20], 'mandatory_product_types' => ['course'],
    ]);
    expect(app(CriterionApplicability::class)->resolve($criterion, $product))
        ->toMatchArray(['applicable' => true, 'weight' => 20.0, 'mandatory' => true]);
});

test('criterion applicability rejects malformed rule collections', function () {
    [$criterion, $product] = applicabilityFixture(['product_types' => 'course']);
    expect(fn () => app(CriterionApplicability::class)->resolve($criterion, $product))
        ->toThrow(DomainStateTransitionException::class);
});

test('criterion applicability rejects negative weight overrides', function () {
    [$criterion, $product] = applicabilityFixture(['weight_overrides' => ['course' => -5]]);
    expect(fn () => app(CriterionApplicability::class)->resolve($criterion, $product))
        ->toThrow(DomainStateTransitionException::class);
});
