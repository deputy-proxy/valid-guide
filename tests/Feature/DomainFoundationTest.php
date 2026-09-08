<?php

use App\Enums\OrganizationRole;
use App\Enums\ProductType;
use App\Models\EvaluationRequest;
use App\Models\EvaluationStandard;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\StandardVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creator domain relationships are wired correctly', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Example Publisher',
        'slug' => 'example-publisher',
        'status' => 'active',
    ]);

    $organization->users()->attach($user, ['role' => OrganizationRole::Owner->value]);

    $product = Product::create([
        'organization_id' => $organization->id,
        'title' => 'Example Course',
        'slug' => 'example-course',
        'product_type' => ProductType::Course,
        'status' => 'active',
    ]);

    $release = ProductRelease::create([
        'product_id' => $product->id,
        'release_identifier' => '2026-01',
        'title_snapshot' => $product->title,
    ]);

    $request = EvaluationRequest::create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'quoted_price' => 500,
        'currency' => 'EUR',
    ]);

    expect($user->organizations)->toHaveCount(1)
        ->and($organization->products->first()->is($product))->toBeTrue()
        ->and($product->releases->first()->is($release))->toBeTrue()
        ->and($request->organization->is($organization))->toBeTrue()
        ->and($request->product->is($product))->toBeTrue();
});

test('a standard version can be approved by a user and associated with evaluations', function () {
    $approver = User::factory()->create();
    $standard = EvaluationStandard::create([
        'name' => 'Valid.guide Validation Standard',
        'slug' => 'valid-guide-validation-standard',
    ]);

    $version = StandardVersion::create([
        'evaluation_standard_id' => $standard->id,
        'version' => '1.0',
        'approved_by' => $approver->id,
        'approved_at' => now(),
    ]);

    expect($version->standard->is($standard))->toBeTrue()
        ->and($version->approver->is($approver))->toBeTrue();
});
