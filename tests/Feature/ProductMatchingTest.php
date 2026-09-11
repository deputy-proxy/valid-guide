<?php

declare(strict_types=1);

use App\Enums\ProductAudience;
use App\Enums\ProductGoal;
use App\Enums\ProductType;
use App\Enums\ValidationStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductMatching;
use Illuminate\Support\Facades\DB;

function issue11Product(string $slug, array $audiences = [], array $goals = []): Product
{
    $user = User::factory()->create();
    $organization = Organization::query()->create(['name' => 'Creator '.$slug, 'slug' => $slug, 'status' => 'active']);
    $organization->users()->attach($user, ['role' => 'editor']);
    $product = Product::query()->create([
        'organization_id' => $organization->getKey(),
        'title' => 'Product '.$slug,
        'slug' => $slug,
        'product_type' => ProductType::Course,
        'subject_area' => 'Leadership',
        'description' => 'Course description.',
        'canonical_url' => 'https://example.com/'.$slug,
        'target_audience' => 'Professionals',
        'language' => 'en',
    ]);
    DB::table('products')->whereKey($product->getKey())->update([
        'matching_audiences' => json_encode($audiences),
        'matching_goals' => json_encode($goals),
    ]);

    return $product->refresh();
}

function issue11Validation(Product $product, string $releaseIdentifier = 'v1', string $status = 'active'): void
{
    $now = now();
    $releaseId = DB::table('product_releases')->insertGetId([
        'product_id' => $product->getKey(),
        'release_identifier' => $releaseIdentifier,
        'title_snapshot' => $product->title,
        'status' => 'current',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $standardId = DB::table('evaluation_standards')->insertGetId([
        'name' => 'Issue 11',
        'slug' => 'issue-11-'.$product->getKey().'-'.$releaseIdentifier,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $versionId = DB::table('standard_versions')->insertGetId([
        'evaluation_standard_id' => $standardId,
        'version' => '1.0',
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $requestId = DB::table('evaluation_requests')->insertGetId([
        'organization_id' => $product->organization_id,
        'product_id' => $product->getKey(),
        'status' => 'draft',
        'currency' => 'EUR',
        'service_package' => 'validation',
        'complexity' => 'standard',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $evaluationId = DB::table('evaluations')->insertGetId([
        'evaluation_request_id' => $requestId,
        'product_id' => $product->getKey(),
        'product_release_id' => $releaseId,
        'standard_version_id' => $versionId,
        'status' => 'completed',
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('validations')->insert([
        'product_release_id' => $releaseId,
        'evaluation_id' => $evaluationId,
        'verification_identifier' => 'issue-11-'.$product->getKey().'-'.$releaseIdentifier,
        'issued_at' => $now,
        'status' => $status,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('matches explicit audience and use case criteria', function () {
    $product = issue11Product('leadership-course', [ProductAudience::Professionals->value], [ProductGoal::ProfessionalDevelopment->value]);
    issue11Validation($product);

    $match = app(ProductMatching::class)->match(ProductAudience::Professionals, ProductGoal::ProfessionalDevelopment, ProductType::Course, 'leadership', 'EN')->first();

    expect($match)->not->toBeNull()
        ->and($match->productId)->toBe($product->getKey())
        ->and($match->validationStatus)->toBe(ValidationStatus::Active)
        ->and($match->suitabilityReasons)->toContain('Audience matches: Professionals.')
        ->and($match->suitabilityReasons)->toContain('Use case matches: Professional Development.');
});

it('rejects explicit suitability mismatches and incomplete metadata', function () {
    $product = issue11Product('student-course', [ProductAudience::Students->value], [ProductGoal::SkillDevelopment->value]);
    issue11Validation($product);
    $incomplete = issue11Product('incomplete-course');
    issue11Validation($incomplete);

    expect(app(ProductMatching::class)->match(audience: ProductAudience::Professionals))->toHaveCount(0)
        ->and(app(ProductMatching::class)->match(goal: ProductGoal::ProfessionalDevelopment))->toHaveCount(0);
});

it('excludes non-current validation states', function (string $status) {
    $product = issue11Product('status-course-'.$status, [ProductAudience::Professionals->value]);
    issue11Validation($product, 'v1', $status);

    expect(app(ProductMatching::class)->match(audience: ProductAudience::Professionals))->toHaveCount(0);
})->with([
    ValidationStatus::Suspended->value,
    ValidationStatus::Revoked->value,
    ValidationStatus::Superseded->value,
]);

it('does not treat validation on an older release as validation of the current release', function () {
    $product = issue11Product('stale-release-course', [ProductAudience::Professionals->value]);
    issue11Validation($product, 'v1');
    DB::table('product_releases')->where('product_id', $product->getKey())->update(['status' => 'superseded']);

    $now = now();
    DB::table('product_releases')->insert([
        'product_id' => $product->getKey(),
        'release_identifier' => 'v2',
        'title_snapshot' => $product->title,
        'status' => 'current',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(app(ProductMatching::class)->match(audience: ProductAudience::Professionals))->toHaveCount(0);
});

it('does not expose private suitability metadata in the match result', function () {
    $product = issue11Product('private-metadata-course', [ProductAudience::Professionals->value], [ProductGoal::ProfessionalDevelopment->value]);
    issue11Validation($product);
    $match = app(ProductMatching::class)->match(audience: ProductAudience::Professionals)->first();

    expect($match)->not->toBeNull()
        ->and(get_object_vars($match))->not->toHaveKey('matching_audiences')
        ->and(get_object_vars($match))->not->toHaveKey('matching_goals');
});

it('keeps matching independent from commercial product data', function () {
    $product = issue11Product('commercial-neutral-course', [ProductAudience::Professionals->value]);
    issue11Validation($product);
    $matching = app(ProductMatching::class);
    $before = $matching->match(audience: ProductAudience::Professionals)->first();
    DB::table('products')->whereKey($product->getKey())->update(['reference_price' => 999.99]);
    $after = $matching->match(audience: ProductAudience::Professionals)->first();

    expect($before?->productId)->toBe($after?->productId)
        ->and($before?->validationStatus)->toBe($after?->validationStatus);
});
