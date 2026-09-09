<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Models\Criterion;
use App\Models\EvaluationStandard;
use App\Models\StandardVersion;
use App\Models\User;
use App\Services\DomainStateTransitionException;
use App\Services\MethodologyRuleValidator;
use App\Services\StandardVersionGovernance;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

test('weight overrides must use supported product types and non-negative numeric values', function () {
    $criterion = methodologyRuleFixture(['weight_overrides' => ['course' => -1]]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateCriterion($criterion))
        ->toThrow(DomainStateTransitionException::class);
});

test('standard versions must contain criteria with unique codes before scheduling', function () {
    $standard = EvaluationStandard::create([
        'name' => 'Empty Rules Standard',
        'slug' => 'empty-rules-standard-'.uniqid(),
    ]);
    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'draft',
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($version))
        ->toThrow(DomainStateTransitionException::class);

    $first = Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'RULE-01',
        'name' => 'First',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 10,
    ]);
    $second = Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'RULE-02',
        'name' => 'Duplicate',
        'category' => 'D1',
        'sequence' => 2,
        'weight' => 10,
    ]);
    $second->code = $first->code;

    $criteriaRelation = Mockery::mock(HasMany::class);
    $criteriaRelation->shouldReceive('get')->once()->andReturn(collect([$first, $second]));
    $versionMock = Mockery::mock(StandardVersion::class)->makePartial();
    $versionMock->shouldReceive('criteria')->once()->andReturn($criteriaRelation);

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($versionMock))
        ->toThrow(DomainStateTransitionException::class);
});

test('standard versions reject duplicate criterion sequences before scheduling', function () {
    $standard = EvaluationStandard::create([
        'name' => 'Sequence Standard',
        'slug' => 'sequence-standard-'.uniqid(),
    ]);
    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'status' => 'draft',
    ]);

    Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'RULE-01',
        'name' => 'First',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 10,
    ]);
    Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'RULE-02',
        'name' => 'Second',
        'category' => 'D2',
        'sequence' => 1,
        'weight' => 10,
    ]);

    expect(fn () => app(MethodologyRuleValidator::class)->validateStandardVersion($version))
        ->toThrow(DomainStateTransitionException::class);
});

test('scheduling validates methodology rules before freezing a version', function () {
    $standard = EvaluationStandard::create([
        'name' => 'Scheduling Rules Standard',
        'slug' => 'scheduling-rules-standard-'.uniqid(),
    ]);
    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'effective_at' => now()->addDay(),
        'status' => 'draft',
    ]);
    Criterion::create([
        'standard_version_id' => $version->id,
        'code' => 'RULE-01',
        'name' => 'Bad rule',
        'category' => 'D1',
        'sequence' => 1,
        'weight' => 10,
        'applicability_rules' => ['unsupported' => true],
    ]);
    $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

    expect(fn () => app(StandardVersionGovernance::class)->schedule($version, $admin))
        ->toThrow(DomainStateTransitionException::class);

    expect($version->fresh()->status->value)->toBe('draft');
});
